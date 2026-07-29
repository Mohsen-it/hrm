<?php

namespace Modules\FingerprintDevices\Services;

use App\Services\ZKTecoPythonBridgeService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\DTOs\FingerprintTemplateData;
use Modules\AttendanceIntegration\DTOs\UserData;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\DevicePushResult;
use Modules\FingerprintDevices\Models\DeviceSyncLog;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\DevicePushResultRepository;
use Modules\FingerprintDevices\Repositories\DeviceSyncLogRepository;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\Users\Models\User;

/**
 * DevicePushService — pushes users and fingerprints FROM the app TO the device.
 *
 * The pull side lives in {@see DeviceFullSyncService}; the two services are
 * intentionally separate (SRP) but share the same DeviceSyncLog persistence
 * so operators can audit full bidirectional runs in a single place.
 */
class DevicePushService
{
    public function __construct(
        private FingerprintDeviceRepository $deviceRepository,
        private DeviceSyncLogRepository $syncLogRepository,
        private DevicePushResultRepository $resultRepository,
        private DeviceAdapterResolver $adapterResolver,
    ) {}

    /**
     * Push the requested artefact types to a single device.
     *
     * @param  array<string, mixed>  $options  Supports: push_users, push_fingerprints, push_face_photos, user_ids, branch_id
     * @param  callable|null  $onProgress  fn(string $step, string $status, string $message, int $percent, array $data)
     * @return array<string, mixed>
     */
    public function push(int $deviceId, array $options, ?int $userId = null, ?callable $onProgress = null): array
    {
        $device = $this->deviceRepository->findById($deviceId);
        if (! $device) {
            throw new \RuntimeException("Device not found: {$deviceId}");
        }

        if (! $device->is_push_enabled) {
            throw new \RuntimeException('Push is not enabled for this device.');
        }

        $adapter = $this->resolveAdapter($device);

        $syncLog = $this->syncLogRepository->create([
            'device_id' => $device->id,
            'user_id' => $userId,
            'direction' => 'push',
            'status' => 'running',
            'started_at' => now(),
            'steps' => [],
            'totals' => [
                'pushed_users' => 0,
                'pushed_fingerprints' => 0,
                'failed_users' => 0,
                'failed_fingerprints' => 0,
                'skipped_users' => 0,
            ],
            'errors' => [],
        ]);

        $this->syncLogRepository->incrementSyncCount($device->id);

        $totals = $syncLog->totals;
        $errors = [];
        $hasFailure = false;

        try {
            $userIds = $this->resolveUserIds($device, $options);

            if (! empty($options['push_users'])) {
                $this->emitProgress($onProgress, 'push_users', 'running', 'جاري دفع المستخدمين...', 25);
                $result = $this->pushUsers($device, $adapter, $userIds, $syncLog, $options);
                $totals = array_merge($totals, $result['totals']);
                $errors = array_merge($errors, $result['errors']);
                if ($result['totals']['failed_users'] > 0) {
                    $hasFailure = true;
                }
                $this->emitProgress($onProgress, 'push_users', 'ok', 'تم دفع المستخدمين', 60, $result['totals']);
            }

            if (! empty($options['push_fingerprints'])) {
                $this->emitProgress($onProgress, 'push_fingerprints', 'running', 'جاري دفع البصمات...', 70);
                $result = $this->pushFingerprints($device, $adapter, $userIds, $syncLog, $options);
                $totals = array_merge($totals, $result['totals']);
                $errors = array_merge($errors, $result['errors']);
                if ($result['totals']['failed_fingerprints'] > 0) {
                    $hasFailure = true;
                }
                $this->emitProgress($onProgress, 'push_fingerprints', 'ok', 'تم دفع البصمات', 85, $result['totals']);
            }

            if (! empty($options['push_face_photos'])) {
                $this->emitProgress($onProgress, 'push_face_photos', 'running', 'جاري دفع صور الوجوه...', 88);
                $result = $this->pushFacePhotos($device, $adapter, $userIds, $syncLog, $options);
                $totals = array_merge($totals, $result['totals']);
                $errors = array_merge($errors, $result['errors']);
                if (($result['totals']['failed_face_photos'] ?? 0) > 0) {
                    $hasFailure = true;
                }
                $this->emitProgress($onProgress, 'push_face_photos', 'ok', 'تم دفع صور الوجوه', 95, $result['totals']);
            }

            $device->update(['last_pushed_at' => now()]);

            $status = $hasFailure ? 'partial' : 'completed';
        } catch (\Throwable $e) {
            Log::error('DevicePushService::push failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            $errors[] = $e->getMessage();
            $status = 'failed';
        }

        $duration = microtime(true) - $syncLog->started_at->getTimestamp();
        $finalized = $this->syncLogRepository->update($syncLog, [
            'status' => $status,
            'finished_at' => now(),
            'duration_seconds' => round($duration, 2),
            'totals' => $totals,
            'errors' => $errors,
        ]);

        if ($onProgress) {
            $onProgress('done', $status, 'اكتملت العملية', 100, $totals);
        }

        return [
            'success' => $status !== 'failed',
            'sync_log_id' => $finalized->id,
            'summary' => $totals,
            'errors' => $errors,
            'duration_seconds' => round($duration, 2),
            'status' => $status,
        ];
    }

    /**
     * Push a list of users to a device.
     *
     * @return array{totals: array<string, int>, errors: array<int, string>}
     */
    public function pushUsers(FingerprintDevice $device, DeviceAdapterInterface $adapter, array $userIds, DeviceSyncLog $syncLog, array $options = []): array
    {
        $totals = [
            'pushed_users' => 0,
            'failed_users' => 0,
            'skipped_users' => 0,
        ];
        $errors = [];
        $rows = [];

        $users = User::query()
            ->whereIn('id', $userIds)
            ->whereNotNull('employee_code')
            ->get(['id', 'employee_code', 'name']);

        if ($users->isEmpty()) {
            $errors[] = 'No users with valid employee_code to push.';

            return ['totals' => $totals, 'errors' => $errors];
        }

        // ZK devices: use bridge batch endpoint (1 connection per 100 users)
        $driver = strtolower($device->deviceType->manufacturer ?? '');
        if (str_contains($driver, 'zkteco') || str_contains($driver, 'zk')) {
            return $this->pushUsersBatch($device, $users, $syncLog, $totals, $errors, $rows);
        }

        // Other devices: individual calls
        $deviceUserMap = $this->getDeviceUserMap($device, $adapter);

        foreach ($users as $user) {
            try {
                $uid = $this->resolveUid($device->id, $user, $deviceUserMap);

                $result = $adapter->addUser(
                    $device->ip_address,
                    $device->port,
                    (string) $device->comm_key,
                    (int) $device->timeout,
                    UserData::fromArray([
                        'uid' => $uid,
                        'user_id' => $user->employee_code,
                        'name' => $user->name,
                    ]),
                );

                $returnedUid = is_int($result) ? $result : $uid;

                if ($result !== false) {
                    $totals['pushed_users']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $user->id, null, 'success', null, $returnedUid ?: null);
                } else {
                    $totals['failed_users']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $user->id, null, 'failed', 'adapter reported failure', $uid ?: null);
                }
            } catch (\Throwable $e) {
                $totals['failed_users']++;
                $msg = substr($e->getMessage(), 0, 1000);
                $errors[] = "User {$user->employee_code}: {$msg}";
                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $user->id, null, 'failed', $msg);
                Log::warning('Push user failed', [
                    'device_id' => $device->id,
                    'user_id' => $user->id,
                    'error' => $msg,
                ]);
            }
        }

        $this->resultRepository->createMany($rows);

        return ['totals' => $totals, 'errors' => $errors];
    }

    /**
     * Push users to ZK device using the bridge's batch endpoint.
     *
     * Chunks users into groups of 100 and calls /device/add-users-batch
     * for each chunk — one device connection per chunk instead of one per user.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, int>  $totals
     * @param  array<int, string>  $errors
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{totals: array<string, int>, errors: array<int, string>}
     */
    private function pushUsersBatch(FingerprintDevice $device, $users, DeviceSyncLog $syncLog, array &$totals, array &$errors, array &$rows): array
    {
        $bridgeUrl = rtrim(config('attendanceintegration.drivers.zkteco.bridge_url'), '/');

        // CRITICAL: Fetch existing device users FIRST to get UIDs
        // This prevents duplicate creation when editing users
        $deviceUserMap = [];
        try {
            $existingResp = Http::timeout(60)->post("{$bridgeUrl}/device/get-users", [
                'ip' => $device->ip_address,
                'port' => $device->port,
                'password' => (int) $device->comm_key,
            ]);
            if ($existingResp->successful()) {
                $existingBody = $existingResp->json() ?? [];
                foreach (($existingBody['users'] ?? []) as $du) {
                    $code = (string) ($du['user_id'] ?? '');
                    if ($code !== '') {
                        $deviceUserMap[$code] = [
                            'uid' => (int) ($du['uid'] ?? 0),
                            'name' => (string) ($du['name'] ?? ''),
                            'privilege' => (int) ($du['privilege'] ?? 0),
                            'password' => (string) ($du['password'] ?? ''),
                            'card' => (int) ($du['card'] ?? 0),
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'Could not fetch existing device users: '.$e->getMessage();
        }

        $chunks = array_chunk($users->all(), 100);

        foreach ($chunks as $chunk) {
            $payload = array_map(function ($u) use ($deviceUserMap) {
                $empCode = (string) $u->employee_code;
                $existing = $deviceUserMap[$empCode] ?? null;

                return [
                    'uid' => $existing ? $existing['uid'] : 0,
                    'user_id' => $empCode,
                    'name' => (string) $u->name,
                    'password' => $existing['password'] ?? '',
                    'privilege' => $existing['privilege'] ?? 0,
                    'card' => $existing['card'] ?? 0,
                ];
            }, $chunk);

            try {
                $resp = Http::timeout(600)->retry(2, 1000)->post("{$bridgeUrl}/device/add-users-batch", [
                    'ip' => $device->ip_address,
                    'port' => $device->port,
                    'password' => (int) $device->comm_key,
                    'users' => $payload,
                ]);
                $body = $resp->json() ?? [];
                $ok = (int) ($body['success_count'] ?? 0);
                $fail = (int) ($body['failed_count'] ?? 0);
                $uidMap = $body['uid_map'] ?? [];
                $totals['pushed_users'] += $ok;
                $totals['failed_users'] += $fail;

                foreach ($chunk as $u) {
                    $empCode = (string) $u->employee_code;
                    $deviceUid = $uidMap[$empCode] ?? $deviceUserMap[$empCode]['uid'] ?? null;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $u->id, null, 'success', null, $deviceUid);
                }
                if (! empty($body['errors'])) {
                    foreach ($body['errors'] as $e) {
                        $errors[] = (string) $e;
                    }
                }
            } catch (\Throwable $e) {
                $totals['failed_users'] += count($chunk);
                $errors[] = 'Batch push failed: '.$e->getMessage();
            }
        }

        $this->resultRepository->createMany($rows);

        return ['totals' => $totals, 'errors' => $errors];
    }

    /**
     * Push fingerprints of the given users to a device.
     *
     * The bridge identifies users on-device by their assigned `uid` (an
     * integer auto-assigned by the device), NOT by the DB `users.id`. We
     * therefore fetch the device's current user list once, build a
     * `user_id (employee_code) → device_uid` map, and use that to resolve
     * each fingerprint's owner.
     *
     * @return array{totals: array<string, int>, errors: array<int, string>}
     */
    public function pushFingerprints(FingerprintDevice $device, DeviceAdapterInterface $adapter, array $userIds, DeviceSyncLog $syncLog, array $options = []): array
    {
        $totals = [
            'pushed_fingerprints' => 0,
            'failed_fingerprints' => 0,
            'skipped_fingerprints' => 0,
            'pushed_face_templates' => 0,
            'failed_face_templates' => 0,
            'skipped_face_templates' => 0,
        ];
        $errors = [];
        $rows = [];

        $allTemplates = UserFingerprint::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->orderByRaw('is_master DESC')
            ->orderBy('finger_id')
            ->get();

        $fingerprints = $allTemplates->reject(fn ($fp) => $this->isFaceTemplate($fp));
        $faceTemplates = $allTemplates->filter(fn ($fp) => $this->isFaceTemplate($fp));

        if ($fingerprints->isEmpty() && $faceTemplates->isEmpty()) {
            $errors[] = 'No fingerprints or face templates found for the selected users.';

            return ['totals' => $totals, 'errors' => $errors];
        }

        Log::info('pushFingerprints: fetching device user list…');
        $deviceUsers = $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            (int) $device->timeout,
        );
        $userIdToUid = [];
        foreach ($deviceUsers as $du) {
            $userIdToUid[(string) ($du['user_id'] ?? '')] = (int) ($du['uid'] ?? 0);
        }

        $empCodeByDbId = User::query()
            ->whereIn('id', $allTemplates->pluck('user_id')->unique())
            ->pluck('employee_code', 'id');

        $driver = strtolower($device->deviceType->manufacturer ?? '');
        $isZk = str_contains($driver, 'zkteco') || str_contains($driver, 'zk');
        $bridgeUrl = $isZk ? rtrim(config('attendanceintegration.drivers.zkteco.bridge_url'), '/') : '';

        if ($fingerprints->isNotEmpty()) {
            $fpPayload = [];
            foreach ($fingerprints as $fp) {
                $empCode = (string) ($empCodeByDbId[$fp->user_id] ?? '');
                $uid = $userIdToUid[$empCode] ?? null;

                if (! $uid) {
                    $totals['skipped_fingerprints']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', $fp->user_id, $fp->finger_id, 'skipped', 'user not on device');

                    continue;
                }

                $fpPayload[] = [
                    'uid' => $uid,
                    'finger_id' => (int) $fp->finger_id,
                    'template_data' => (string) $fp->template_data,
                ];
            }

            if (! empty($fpPayload)) {
                if ($isZk) {
                    $chunks = array_chunk($fpPayload, 100);
                    foreach ($chunks as $chunk) {
                        try {
                            $resp = Http::timeout(600)->retry(2, 1000)->post("{$bridgeUrl}/device/export-templates-batch", [
                                'ip' => $device->ip_address,
                                'port' => $device->port,
                                'password' => (int) $device->comm_key,
                                'templates' => $chunk,
                            ]);
                            $body = $resp->json() ?? [];
                            $totals['pushed_fingerprints'] += (int) ($body['success_count'] ?? 0);
                            $totals['failed_fingerprints'] += (int) ($body['failed_count'] ?? 0);

                            foreach ($chunk as $t) {
                                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', null, $t['finger_id'], 'success', null);
                            }
                            if (! empty($body['results'])) {
                                foreach (array_filter($body['results'], fn ($r) => empty($r['success'])) as $r) {
                                    $errors[] = 'uid='.($r['uid'] ?? '?').' fid='.($r['finger_id'] ?? '?').': '.($r['error'] ?? 'unknown');
                                }
                            }
                        } catch (\Throwable $e) {
                            $totals['failed_fingerprints'] += count($chunk);
                            $errors[] = 'Batch fp push failed: '.$e->getMessage();
                        }
                    }
                } else {
                    foreach ($fpPayload as $fp) {
                        try {
                            $ok = $adapter->setFingerprintTemplate(
                                $device->ip_address,
                                $device->port,
                                (string) $device->comm_key,
                                (int) $device->timeout,
                                new FingerprintTemplateData(
                                    uid: $fp['uid'],
                                    fingerId: $fp['finger_id'],
                                    templateData: $fp['template_data'],
                                ),
                            );
                            if ($ok) {
                                $totals['pushed_fingerprints']++;
                                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', null, $fp['finger_id'], 'success', null);
                            } else {
                                $totals['failed_fingerprints']++;
                                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', null, $fp['finger_id'], 'failed', 'adapter returned false');
                            }
                        } catch (\Throwable $e) {
                            $totals['failed_fingerprints']++;
                            $errors[] = "Fingerprint push failed for finger {$fp['finger_id']}: {$e->getMessage()}";
                        }
                    }
                }
            }
        }

        if ($faceTemplates->isNotEmpty() && $isZk) {
            $facePayload = [];
            foreach ($faceTemplates as $fp) {
                $empCode = (string) ($empCodeByDbId[$fp->user_id] ?? '');
                $uid = $userIdToUid[$empCode] ?? null;

                if (! $uid) {
                    $totals['skipped_face_templates']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_template', $fp->user_id, $fp->finger_id, 'skipped', 'user not on device');

                    continue;
                }

                $facePayload[] = [
                    'uid' => $uid,
                    'user_id' => $empCode,
                    'finger_id' => (int) $fp->finger_id,
                    'template_data' => (string) $fp->template_data,
                ];
            }

            if (! empty($facePayload)) {
                $chunks = array_chunk($facePayload, 20);
                foreach ($chunks as $chunk) {
                    try {
                        $resp = Http::timeout(600)->retry(2, 1000)->post("{$bridgeUrl}/device/push-face-templates-batch", [
                            'ip' => $device->ip_address,
                            'port' => $device->port,
                            'password' => (int) $device->comm_key,
                            'templates' => $chunk,
                        ]);
                        $body = $resp->json() ?? [];
                        $totals['pushed_face_templates'] += (int) ($body['success_count'] ?? 0);
                        $totals['failed_face_templates'] += (int) ($body['failed_count'] ?? 0);

                        foreach ($chunk as $t) {
                            $status = 'success';
                            $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_template', $t['user_id'] ?? null, $t['finger_id'], $status, null);
                        }
                        if (! empty($body['results'])) {
                            foreach (array_filter($body['results'], fn ($r) => empty($r['success'])) as $r) {
                                $errors[] = 'face uid='.($r['uid'] ?? '?').' fid='.($r['finger_id'] ?? '?').': '.($r['error'] ?? 'unknown');
                            }
                        }
                    } catch (\Throwable $e) {
                        $totals['failed_face_templates'] += count($chunk);
                        $errors[] = 'Batch face template push failed: '.$e->getMessage();
                    }
                }
            }
        } elseif ($faceTemplates->isNotEmpty() && ! $isZk) {
            $totals['skipped_face_templates'] = $faceTemplates->count();
            $errors[] = 'Face template push only supported for ZKTeco devices.';
        }

        $this->resultRepository->createMany($rows);

        return ['totals' => $totals, 'errors' => $errors];
    }

    /**
     * Push face photos (JPEG images) to a device.
    {
        $totals = [
            'pushed_face_photos' => 0,
            'failed_face_photos' => 0,
            'skipped_face_photos' => 0,
        ];
        $errors = [];
        $rows = [];

        // Get face photos (face_photo_path) and face templates (finger_id 50-54)
        $faceRecords = UserFingerprint::query()
            ->whereIn('user_id', $userIds)
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereBetween('finger_id', [50, 54])
                        ->whereNotNull('template_data')
                        ->where('template_data', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->where('template_format', 'face_photo')
                        ->whereNotNull('face_photo_path')
                        ->where('face_photo_path', '!=', '');
                });
            })
            ->orderBy('user_id')
            ->orderBy('finger_id')
            ->get();

        if ($faceRecords->isEmpty()) {
            $errors[] = 'No face records found for the selected users.';

            return ['totals' => $totals, 'errors' => $errors];
        }

        // Check if this is a ZKTeco device
        $driver = strtolower($device->deviceType->manufacturer ?? '');
        $isZk = str_contains($driver, 'zkteco') || str_contains($driver, 'zk');

        if (! $isZk) {
            $errors[] = 'Face photo push is only supported for ZKTeco devices.';

            return ['totals' => $totals, 'errors' => $errors];
        }

        // Build employee_code -> device_uid map
        $deviceUsers = $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            (int) $device->timeout,
        );
        $userIdToUid = [];
        foreach ($deviceUsers as $du) {
            $userIdToUid[strtolower((string) ($du['user_id'] ?? ''))] = (int) ($du['uid'] ?? 0);
        }

        $empCodeByDbId = User::query()
            ->whereIn('id', $faceRecords->pluck('user_id')->unique())
            ->pluck('employee_code', 'id');

        // Collect face photos to push via Python bridge
        $photoPayload = [];
        foreach ($faceRecords as $fp) {
            $empCode = (string) ($empCodeByDbId[$fp->user_id] ?? '');
            $uid = $userIdToUid[strtolower($empCode)] ?? null;

            if (! $uid) {
                $totals['skipped_face_photos']++;
                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_photo', $fp->user_id, $fp->finger_id, 'skipped', 'user not on device');

                continue;
            }

            // Face photo from filesystem
            if ($fp->template_format === 'face_photo' && $fp->face_photo_path) {
                $photoPath = storage_path('app/'.$fp->face_photo_path);
                if (file_exists($photoPath)) {
                    $photoBase64 = base64_encode(file_get_contents($photoPath));
                    $photoPayload[] = [
                        'uid' => $uid,
                        'photo_base64' => $photoBase64,
                    ];
                } else {
                    $totals['skipped_face_photos']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_photo', $fp->user_id, $fp->finger_id, 'skipped', 'photo file not found');
                }
            }
        }

        if (empty($photoPayload)) {
            $this->resultRepository->createMany($rows);

            return ['totals' => $totals, 'errors' => $errors];
        }

        // Push via Python bridge batch endpoint
        try {
            $bridge = app(ZKTecoPythonBridgeService::class);
            $result = $bridge->pushFacePhotosBatch(
                $device->ip_address,
                (int) $device->port,
                (string) $device->comm_key,
                $photoPayload,
            );

            if ($result['success'] ?? false) {
                $totals['pushed_face_photos'] = $result['success_count'] ?? 0;
                $totals['failed_face_photos'] = $result['fail_count'] ?? 0;
                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_photo', null, null, 'success', "Pushed {$totals['pushed_face_photos']} face photos");
            } else {
                $totals['failed_face_photos'] = count($photoPayload);
                $errors[] = $result['error'] ?? 'Python bridge returned failure';
                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_photo', null, null, 'failed', $result['error'] ?? 'Unknown error');
            }
        } catch (\Throwable $e) {
            $totals['failed_face_photos'] = count($photoPayload);
            $errors[] = $e->getMessage();
            $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'face_photo', null, null, 'failed', $e->getMessage());
        }

        $this->resultRepository->createMany($rows);

        return ['totals' => $totals, 'errors' => $errors];
    }

    /**
     * Push users restricted to a specific branch.
     */
    public function pushUsersByBranch(int $deviceId, int $branchId, array $options, ?int $userId = null, ?callable $onProgress = null): array
    {
        $userIds = User::query()
            ->where('branch_id', $branchId)
            ->where('is_active_employee', true)
            ->pluck('id')
            ->toArray();

        $options['user_ids'] = $userIds;

        return $this->push($deviceId, $options, $userId, $onProgress);
    }

    /**
     * Push users that don't have a UID on the device yet.
     */
    public function pushUsersMissing(int $deviceId, array $options, ?int $userId = null, ?callable $onProgress = null): array
    {
        // "Missing" = those without a recent successful push result
        $recentPushed = DevicePushResult::query()
            ->forDevice($deviceId)
            ->successful()
            ->ofType('user')
            ->where('attempted_at', '>=', now()->subDay())
            ->pluck('target_user_id')
            ->unique()
            ->toArray();

        $userIds = User::query()
            ->whereNotNull('employee_code')
            ->where('is_active_employee', true)
            ->whereNotIn('id', $recentPushed)
            ->pluck('id')
            ->toArray();

        $options['user_ids'] = $userIds;

        return $this->push($deviceId, $options, $userId, $onProgress);
    }

    /**
     * Retry the failed records of a previous sync log.
     */
    public function retryFailed(int $syncLogId, int $maxRetries = 1, ?int $userId = null, ?callable $onProgress = null): array
    {
        $originalLog = $this->syncLogRepository->findById($syncLogId);
        if (! $originalLog) {
            throw new \RuntimeException("Sync log not found: {$syncLogId}");
        }

        $failedResults = $this->resultRepository->getFailedForLog($syncLogId);
        if ($failedResults->isEmpty()) {
            return [
                'success' => true,
                'retried_count' => 0,
                'succeeded' => 0,
                'still_failing' => 0,
                'message' => 'No failed records to retry.',
            ];
        }

        $skippedOverLimit = 0;
        $retryable = [];
        foreach ($failedResults as $result) {
            if ($result->retry_count >= $maxRetries) {
                $skippedOverLimit++;

                continue;
            }
            $retryable[$result->record_type][] = $result;
        }

        $device = $originalLog->device;
        $adapter = $this->resolveAdapter($device);
        $deviceUserMap = $this->getDeviceUserMap($device, $adapter);

        // Create a new sync log for the retry
        $newLog = $this->syncLogRepository->create([
            'device_id' => $device->id,
            'user_id' => $userId ?? $originalLog->user_id,
            'direction' => 'push',
            'status' => 'running',
            'started_at' => now(),
            'totals' => ['succeeded' => 0, 'still_failing' => 0, 'skipped' => 0],
        ]);

        $succeeded = 0;
        $stillFailing = 0;

        DB::transaction(function () use ($retryable, $device, $adapter, $deviceUserMap, &$succeeded, &$stillFailing) {
            foreach (($retryable['user'] ?? []) as $result) {
                $user = User::find($result->target_user_id);
                if (! $user) {
                    continue;
                }

                try {
                    $uid = $this->resolveUid($device->id, $user, $deviceUserMap);

                    $addResult = $adapter->addUser(
                        $device->ip_address,
                        $device->port,
                        (string) $device->comm_key,
                        (int) $device->timeout,
                        UserData::fromArray([
                            'uid' => $uid,
                            'user_id' => $user->employee_code,
                            'name' => $user->name,
                        ]),
                    );

                    $returnedUid = is_int($addResult) ? $addResult : $uid;

                    if ($addResult !== false) {
                        $result->update(['status' => 'success', 'error_message' => null, 'device_uid' => $returnedUid ?: null]);
                        $this->resultRepository->incrementRetry($result->id);
                        $succeeded++;
                    } else {
                        $result->update(['status' => 'failed', 'error_message' => 'Retry failed']);
                        $this->resultRepository->incrementRetry($result->id);
                        $stillFailing++;
                    }
                } catch (\Throwable $e) {
                    $result->update(['status' => 'failed', 'error_message' => substr($e->getMessage(), 0, 1000)]);
                    $this->resultRepository->incrementRetry($result->id);
                    $stillFailing++;
                }
            }
        });

        $this->syncLogRepository->update($newLog, [
            'status' => $stillFailing > 0 ? 'partial' : 'completed',
            'finished_at' => now(),
            'duration_seconds' => now()->diffInSeconds($newLog->started_at, true),
            'totals' => [
                'succeeded' => $succeeded,
                'still_failing' => $stillFailing,
                'skipped_over_limit' => $skippedOverLimit,
            ],
        ]);

        return [
            'success' => true,
            'sync_log_id' => $newLog->id,
            'retried_count' => $failedResults->count(),
            'succeeded' => $succeeded,
            'still_failing' => $stillFailing,
            'skipped_over_limit' => $skippedOverLimit,
        ];
    }

    /**
     * Resolve the user IDs to push based on options.
     *
     * @return array<int, int>
     */
    private function resolveUserIds(FingerprintDevice $device, array $options): array
    {
        if (! empty($options['user_ids']) && is_array($options['user_ids'])) {
            return array_map('intval', $options['user_ids']);
        }

        $mode = (string) ($options['select_mode'] ?? '');

        if ($mode === 'missing') {
            return $this->resolveMissingUserIds($device, $options);
        }

        if ($mode === 'branch' || ! empty($options['branch_id'])) {
            $branchId = (int) ($options['branch_id'] ?? 0);
            if ($branchId > 0) {
                return User::query()
                    ->where('branch_id', $branchId)
                    ->where('is_active_employee', true)
                    ->pluck('id')
                    ->toArray();
            }
        }

        // Default: all active employees
        return User::query()
            ->where('is_active_employee', true)
            ->pluck('id')
            ->toArray();
    }

    private function resolveAdapter(FingerprintDevice $device): DeviceAdapterInterface
    {
        $typeName = strtolower($device->deviceType->manufacturer ?? '');

        $driver = match (true) {
            str_contains($typeName, 'zkteco'), str_contains($typeName, 'zk') => 'zkteco',
            str_contains($typeName, 'suprema') => 'suprema',
            str_contains($typeName, 'hikvision'), str_contains($typeName, 'hik') => 'hikvision',
            default => config('attendanceintegration.default_driver', 'zkteco'),
        };

        return $this->adapterResolver->getAdapter($driver);
    }

    /**
     * Resolve user IDs that exist in DB but NOT on the device yet.
     *
     * Strategy: query the device for its current user list, build a set
     * of `user_id` (employee_code) strings, then find active DB users
     * whose `employee_code` is not in that set.
     *
     * @return array<int, int>
     */
    private function resolveMissingUserIds(FingerprintDevice $device, array $options): array
    {
        $adapter = $this->resolveAdapter($device);

        $deviceUsers = $adapter->getUsers(
            $device->ip_address,
            $device->port,
            (string) $device->comm_key,
            (int) $device->timeout,
        );

        $existingCodes = [];
        foreach ($deviceUsers as $du) {
            $code = (string) ($du['user_id'] ?? '');
            if ($code !== '') {
                $existingCodes[$code] = true;
            }
        }

        $query = User::query()
            ->where('is_active_employee', true)
            ->where('status', 1)
            ->whereNotNull('employee_code')
            ->where('employee_code', '!=', '');

        if (! empty($options['branch_id'])) {
            $query->where('branch_id', (int) $options['branch_id']);
        }

        return $query
            ->whereNotIn('employee_code', array_keys($existingCodes))
            ->pluck('id')
            ->all();
    }

    private function resolveUid(int $deviceId, User $user, array $deviceUserMap): int
    {
        // 1. Try to match by current employee_code from the device's live user list
        if (isset($deviceUserMap[(string) $user->employee_code])) {
            return (int) $deviceUserMap[(string) $user->employee_code];
        }

        // 2. Try to find the UID from previous successful push results for this user on this device
        $previousUid = DevicePushResult::query()
            ->where('device_id', $deviceId)
            ->where('target_user_id', $user->id)
            ->where('record_type', 'user')
            ->where('status', 'success')
            ->whereNotNull('device_uid')
            ->orderBy('id', 'desc')
            ->value('device_uid');

        if ($previousUid) {
            return (int) $previousUid;
        }

        return 0;
    }

    private function getDeviceUserMap(FingerprintDevice $device, DeviceAdapterInterface $adapter): array
    {
        try {
            $deviceUsers = $adapter->getUsers(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) $device->timeout,
            );

            $map = [];
            foreach ($deviceUsers as $du) {
                $code = (string) ($du['user_id'] ?? '');
                if ($code !== '') {
                    $map[$code] = (int) ($du['uid'] ?? 0);
                }
            }

            return $map;
        } catch (\Throwable $e) {
            Log::warning('getDeviceUserMap failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function emitProgress(?callable $onProgress, string $step, string $status, string $message, int $percent, array $data = []): void
    {
        if ($onProgress) {
            $onProgress($step, $status, $message, $percent, $data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResultRow(int $syncLogId, int $deviceId, string $type, ?int $userId, ?int $fingerId, string $status, ?string $error, ?int $uid = null): array
    {
        return [
            'sync_log_id' => $syncLogId,
            'device_id' => $deviceId,
            'record_type' => $type,
            'target_user_id' => $userId,
            'target_finger_id' => $fingerId,
            'device_uid' => $uid,
            'status' => $status,
            'error_message' => $error,
            'attempted_at' => now(),
            'retry_count' => 0,
        ];
    }

    private function isFaceTemplate(UserFingerprint $fp): bool
    {
        $format = $fp->template_format ?? '';
        $fingerId = $fp->finger_id ?? 0;

        return str_contains($format, 'face')
            || str_contains($format, 'zkteco-face')
            || $fingerId >= 50;
    }
}
