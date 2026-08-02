<?php

namespace Modules\FingerprintDevices\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;
use Modules\FingerprintDevices\Support\AppliesDeviceOrgDefaults;
use Modules\Users\Models\User;

/**
 * DeviceFullSyncService — one-shot end-to-end pull from a ZKTeco device.
 *
 * Steps performed (each opt-in via the $options array):
 *  1. `info`        — refresh device metadata and counters.
 *  2. `users`       — pull the user list from the device, match against
 *                     `users.employee_code`, and remember the mapping.
 *  3. `fingerprints`— for every matched user, download each registered
 *                     template and persist it in `user_fingerprints`.
 *  4. `attendance`  — pull raw punches, store them in
 *                     `raw_attendance_logs`, and (best effort) reconcile
 *                     them into `attendance_sessions`.
 *
 * The service returns a structured result array that the controller hands
 * off to the Vue page; partial failures are recorded per step and do not
 * abort the whole sync.
 */
class DeviceFullSyncService
{
    use AppliesDeviceOrgDefaults;

    public function __construct(
        private FingerprintDeviceRepository $deviceRepository,
        private UserFingerprintRepository $fingerprintRepository,
        private DeviceAdapterResolver $adapterResolver,
        private RawAttendanceLogService $rawLogService,
    ) {}

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
     * Run the requested sync steps and return a result payload.
     *
     * @param  array<string, mixed>  $options  toggles + date range
     * @param  callable|null  $onProgress  optional callback: fn(string $step, string $status, string $message, int $percent, array $data)
     * @return array<string, mixed>
     */
    public function run(FingerprintDevice $device, array $options = [], ?callable $onProgress = null): array
    {
        $options = array_merge([
            'info' => true,
            'users' => true,
            'fingerprints' => true,
            'face_photos' => true,
            'attendance' => true,
            'clear_local_cache' => false,
        ], $options);

        $startedAt = microtime(true);

        $adapter = $this->resolveAdapter($device);

        $result = [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'serial_number' => $device->serial_number,
            'started_at' => now()->toDateTimeString(),
            'steps' => [],
            'totals' => [
                'users_on_device' => 0,
                'users_matched' => 0,
                'users_unmatched' => 0,
                'fingerprints_pulled' => 0,
                'fingerprints_saved' => 0,
                'fingerprints_removed' => 0,
                'face_photos_pulled' => 0,
                'face_photos_saved' => 0,
                'attendance_pulled' => 0,
                'attendance_saved' => 0,
                'attendance_sessions' => 0,
            ],
            'unmatched_users' => [],
            'errors' => [],
            'duration_seconds' => 0,
        ];

        $stepsToRun = array_filter([
            $options['info'] ? 'info' : null,
            $options['users'] ? 'users' : null,
            $options['fingerprints'] ? 'fingerprints' : null,
            $options['face_photos'] ? 'face_photos' : null,
            $options['attendance'] ? 'attendance' : null,
        ]);
        $totalSteps = count($stepsToRun);
        $currentStep = 0;

        $notifyProgress = function (string $step, string $status, string $message = '', array $data = []) use ($onProgress, &$currentStep, $totalSteps): void {
            $currentStep++;
            $percent = $totalSteps > 0 ? (int) round(($currentStep / $totalSteps) * 100) : 0;
            if ($onProgress) {
                $onProgress($step, $status, $message, $percent, $data);
            }
        };

        try {
            $this->emitProgress($onProgress, 'info', 'running', '...', 0);
            $info = $this->stepInfo($device, $result, (bool) $options['info'], $adapter);
            if (is_array($info)) {
                $result['device_info'] = $info;
            }
            $notifyProgress('info', end($result['steps'])['status'] ?? 'ok', end($result['steps'])['message'] ?? '');

            $this->emitProgress($onProgress, 'users', 'running', 'جاري مزامنة الموظفين...', 25);
            $matched = $this->stepUsers($device, $result, (bool) $options['users'], $adapter);
            $notifyProgress('users', end($result['steps'])['status'] ?? 'ok', end($result['steps'])['message'] ?? '');

            $this->emitProgress($onProgress, 'fingerprints', 'running', 'جاري سحب البصمات...', 50);
            $this->stepFingerprints(
                $device,
                $matched,
                $result,
                (bool) $options['fingerprints'],
                (bool) $options['clear_local_cache'],
                $adapter
            );
            $notifyProgress('fingerprints', end($result['steps'])['status'] ?? 'ok', end($result['steps'])['message'] ?? '');

            $this->emitProgress($onProgress, 'face_photos', 'running', 'جاري سحب صور الوجوه...', 65);
            $this->stepFacePhotos(
                $device,
                $matched,
                $result,
                (bool) $options['face_photos'],
                $adapter
            );
            $notifyProgress('face_photos', end($result['steps'])['status'] ?? 'ok', end($result['steps'])['message'] ?? '');

            $this->emitProgress($onProgress, 'attendance', 'running', 'جاري سحب سجلات الحضور...', 75);
            $this->stepAttendance(
                $device,
                $matched,
                $result,
                (bool) $options['attendance'],
                $adapter
            );
            $notifyProgress('attendance', end($result['steps'])['status'] ?? 'ok', end($result['steps'])['message'] ?? '');

            $device = $this->deviceRepository->updateSyncTimestamp($device);
        } catch (\Throwable $e) {
            Log::error('DeviceFullSyncService failed', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
            $result['errors'][] = $e->getMessage();
        }

        $result['duration_seconds'] = round(microtime(true) - $startedAt, 2);
        $result['finished_at'] = now()->toDateTimeString();

        if ($onProgress) {
            $onProgress('done', 'ok', 'اكتملت المزامنة', 100, $result['totals']);
        }

        return $result;
    }

    /**
     * Send a progress notification through the callback.
     */
    private function emitProgress(?callable $onProgress, string $step, string $status, string $message, int $percent): void
    {
        if ($onProgress) {
            $onProgress($step, $status, $message, $percent, []);
        }
    }

    /**
     * Step 1 — pull device metadata and update counters.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    protected function stepInfo(FingerprintDevice $device, array &$result, bool $enabled, DeviceAdapterInterface $adapter): ?array
    {
        $step = ['name' => 'info', 'status' => 'skipped', 'message' => null];

        if (! $enabled) {
            $result['steps'][] = $step;

            return null;
        }

        try {
            $info = $adapter->getDeviceInfo(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
            $info = is_array($info) ? $info : [];

            $payload = [
                'user_count' => (int) ($info['users_count'] ?? 0),
                'fingerprint_count' => (int) ($info['templates_count'] ?? 0),
                'attendance_log_count' => (int) ($info['attendance_count'] ?? 0),
            ];

            if (! empty($info['serialnumber'])) {
                $payload['serial_number'] = (string) $info['serialnumber'];
            }

            $device->update($payload);
            $device->refresh();

            $result['totals']['users_on_device'] = $payload['user_count'];

            $step['status'] = 'ok';
            $step['message'] = 'Device info refreshed';
            $step['data'] = [
                'firmware' => $info['firmware'] ?? null,
                'platform' => $info['platform'] ?? null,
                'device_name' => $info['device_name'] ?? null,
                'serial' => $info['serialnumber'] ?? null,
                'user_count' => $payload['user_count'],
                'fingerprint_count' => $payload['fingerprint_count'],
                'attendance_log_count' => $payload['attendance_log_count'],
            ];

            $result['steps'][] = $step;

            return $info;
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['steps'][] = $step;
            $result['errors'][] = 'info: '.$e->getMessage();

            return null;
        }
    }

    /**
     * Step 2 — pull users from the device and match them to HRM users.
     *
     * Matching strategy: `users.employee_code == device.user_id`. The
     * ZKTeco adapter returns the `user_id` as it was stored on the device
     * — the system relies on operators pre-loading employees with their
     * `employee_code` set to the same value.
     *
     * @param  array<string, mixed>  $result
     * @return array<int, array{uid:int,user_id:string,name:string,user_pk:?int}>
     */
    protected function stepUsers(FingerprintDevice $device, array &$result, bool $enabled, DeviceAdapterInterface $adapter): array
    {
        $step = ['name' => 'users', 'status' => 'skipped', 'message' => null];

        if (! $enabled) {
            $result['steps'][] = $step;

            return [];
        }

        try {
            $deviceUsers = $adapter->getUsers(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
            $deviceUsers = is_array($deviceUsers) ? $deviceUsers : [];

            // Pre-fetch all HRM users by employee_code (case-insensitive) in one query
            $externalIds = array_filter(array_map(fn ($du) => trim((string) ($du['user_id'] ?? '')), $deviceUsers), fn ($id) => $id !== '');
            $uniqueExternalIds = array_unique($externalIds);

            $userMapByCode = [];
            $userMapByName = [];
            if (! empty($uniqueExternalIds)) {
                $lowerCodes = array_map('strtolower', $uniqueExternalIds);
                $placeholders = implode(',', array_fill(0, count($lowerCodes), '?'));
                $users = User::query()
                    ->whereRaw("LOWER(employee_code) IN ($placeholders)", $lowerCodes)
                    ->get();
                foreach ($users as $u) {
                    $userMapByCode[strtolower((string) $u->employee_code)] = $u;
                }

                // Also build a name-based index for fallback matching
                $allUsers = User::query()
                    ->where('status', 1)
                    ->where('is_active_employee', true)
                    ->select('id', 'name', 'full_name_ar', 'employee_code')
                    ->get();
                foreach ($allUsers as $u) {
                    if ($u->full_name_ar) {
                        $userMapByName[$u->full_name_ar] = $u;
                    }
                    if ($u->name) {
                        $userMapByName[$u->name] = $u;
                    }
                }
            }

            $matched = [];
            $unmatched = [];
            $created = 0;

            foreach ($deviceUsers as $du) {
                $uid = (int) ($du['uid'] ?? 0);
                $externalId = trim((string) ($du['user_id'] ?? ''));
                $name = (string) ($du['name'] ?? '');

                if ($externalId === '') {
                    $unmatched[] = [
                        'uid' => $uid,
                        'user_id' => $externalId,
                        'name' => $name,
                        'reason' => 'empty user_id',
                    ];

                    continue;
                }

                // Try matching by employee_code (case-insensitive) from pre-fetched map
                $user = $userMapByCode[strtolower($externalId)] ?? null;

                // Fallback: match by name from pre-fetched map
                if (! $user && $name !== '') {
                    $user = $userMapByName[$name] ?? null;
                }

                if (! $user) {
                    $autoName = $name !== '' ? $name : 'User '.$externalId;

                    $emailBase = 'device_'.strtolower($externalId).'@hrm.local';
                    $email = $emailBase;
                    $suffix = 1;
                    while (User::where('email', $email)->exists()) {
                        $email = 'device_'.strtolower($externalId).'_'.$suffix.'@hrm.local';
                        $suffix++;
                    }

                    $userData = [
                        'employee_code' => $externalId,
                        'name' => $autoName,
                        'full_name_ar' => $autoName,
                        'email' => $email,
                        'password' => bcrypt('password'),
                        'status' => 1,
                        'is_active_employee' => true,
                    ];

                    $userData = $this->applyDeviceOrgDefaults($device, $userData);

                    try {
                        $user = User::create($userData);
                        $created++;
                    } catch (\Throwable $e) {
                        if (str_contains($e->getMessage(), 'Duplicate entry')) {
                            $email = 'device_'.strtolower($externalId).'_'.(time()).'@hrm.local';
                            $userData['email'] = $email;
                            $user = User::create($userData);
                            $created++;
                        } else {
                            throw $e;
                        }
                    }
                }

                $matched[] = [
                    'uid' => $uid,
                    'user_id' => $externalId,
                    'name' => $name,
                    'user_pk' => (int) $user->id,
                ];
            }

            $result['totals']['users_matched'] = count($matched);
            $result['totals']['users_unmatched'] = count($unmatched);
            $result['unmatched_users'] = $unmatched;

            $step['status'] = 'ok';
            $step['message'] = sprintf(
                '%d matched, %d created, %d unmatched',
                count($matched),
                $created,
                count($unmatched)
            );
            $step['data'] = [
                'total_on_device' => count($deviceUsers),
                'matched' => count($matched),
                'created' => $created,
                'unmatched' => count($unmatched),
            ];
            $result['steps'][] = $step;

            return $matched;
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['steps'][] = $step;
            $result['errors'][] = 'users: '.$e->getMessage();

            return [];
        }
    }

    /**
     * Step 3 — for each matched user, pull every fingerprint template.
     *
     * @param  array<int, array{uid:int,user_id:string,name:string,user_pk:?int}>  $matched
     * @param  array<string, mixed>  $result
     */
    protected function stepFingerprints(
        FingerprintDevice $device,
        array $matched,
        array &$result,
        bool $enabled,
        bool $clearLocal,
        DeviceAdapterInterface $adapter,
    ): void {
        $step = [
            'name' => 'fingerprints',
            'status' => $enabled ? 'running' : 'skipped',
            'message' => null,
        ];

        if (! $enabled || empty($matched)) {
            $step['status'] = $enabled ? 'ok' : 'skipped';
            $step['message'] = $enabled ? 'no matched users to fetch' : 'skipped';
            $result['steps'][] = $step;

            return;
        }

        $pulled = 0;
        $saved = 0;
        $removed = 0;
        $errors = [];

        try {
            // Fetch ALL templates from the device ONCE (much faster than per-user)
            $allTemplates = $adapter->getAllFingerprintTemplates(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
            $allTemplates = is_array($allTemplates) ? $allTemplates : [];

            // Index templates by uid for fast lookup
            $templatesByUid = [];
            foreach ($allTemplates as $tpl) {
                $uid = (int) ($tpl['uid'] ?? 0);
                $templatesByUid[$uid][] = $tpl;
            }

            // Build lookup: device uid => user_pk from matched
            $uidToUserPk = [];
            foreach ($matched as $entry) {
                $uidToUserPk[(int) $entry['uid']] = (int) $entry['user_pk'];
            }

            // Get all user IDs that already have fingerprints stored
            $userIdsWithFingerprints = UserFingerprint::query()
                ->whereIn('user_id', array_column($matched, 'user_pk'))
                ->whereNull('deleted_at')
                ->pluck('user_id')
                ->unique()
                ->flip()
                ->toArray();

            // Bulk-fetch existing fingerprints for this device to avoid per-fingerprint queries
            $existingFingerprints = UserFingerprint::query()
                ->where('device_id', $device->id)
                ->whereIn('user_id', array_column($matched, 'user_pk'))
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(fn ($fp) => $fp->user_id.'_'.$fp->finger_id);

            foreach ($matched as $entry) {
                $userPk = (int) $entry['user_pk'];
                $uid = (int) $entry['uid'];

                // Skip users who already have fingerprints stored
                if (! $clearLocal && isset($userIdsWithFingerprints[$userPk])) {
                    continue;
                }

                if ($clearLocal) {
                    $removed += $this->fingerprintRepository->deleteForUser($userPk);
                }

                // Get templates for this user from the pre-fetched index
                $templates = $templatesByUid[$uid] ?? [];

                foreach ($templates as $tpl) {
                    $pulled++;

                    $templateData = (string) ($tpl['template'] ?? '');
                    if ($templateData === '') {
                        continue;
                    }

                    $fingerId = (int) ($tpl['fid'] ?? 0);

                    $payload = [
                        'user_id' => $userPk,
                        'device_id' => $device->id,
                        'finger_id' => $fingerId,
                        'template_data' => $templateData,
                        'template_format' => 'zkteco-base64',
                        'template_version' => 9,
                        'quality' => (int) ($tpl['valid'] ?? 1) === 1 ? 0 : 0,
                        'is_master' => $fingerId === 0,
                        'captured_at' => now(),
                        'synced_at' => now(),
                    ];

                    $existing = $existingFingerprints->get($userPk.'_'.$fingerId);

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        UserFingerprint::create($payload);
                    }

                    $saved++;
                }
            }

            if ($saved > 0) {
                $device->update(['fingerprint_count' => $saved]);
            }
            $result['totals']['fingerprints_pulled'] = $pulled;
            $result['totals']['fingerprints_saved'] = $saved;
            $result['totals']['fingerprints_removed'] = $removed;

            $step['status'] = 'ok';
            $step['message'] = sprintf(
                '%d pulled, %d saved, %d removed',
                $pulled,
                $saved,
                $removed
            );
            $step['data'] = [
                'pulled' => $pulled,
                'saved' => $saved,
                'removed' => $removed,
            ];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['errors'][] = 'fingerprints: '.$e->getMessage();
        }

        $result['steps'][] = $step;
    }

    /**
     * Step 3b — pull face photos from the device and save to disk.
     *
     * @param  array<int, array{uid:int,user_id:string,name:string,user_pk:?int}>  $matched
     * @param  array<string, mixed>  $result
     */
    protected function stepFacePhotos(
        FingerprintDevice $device,
        array $matched,
        array &$result,
        bool $enabled,
        DeviceAdapterInterface $adapter,
    ): void {
        $step = [
            'name' => 'face_photos',
            'status' => $enabled ? 'running' : 'skipped',
            'message' => null,
        ];

        if (! $enabled) {
            $step['message'] = 'skipped';
            $result['steps'][] = $step;

            return;
        }

        try {
            $photos = $adapter->getFacePhotos(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
            $photos = is_array($photos) ? $photos : [];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['errors'][] = 'face_photos: '.$e->getMessage();
            $result['steps'][] = $step;

            return;
        }

        if (empty($photos)) {
            $step['status'] = 'ok';
            $step['message'] = 'no face photos available';
            $step['data'] = ['pulled' => 0, 'saved' => 0];
            $result['steps'][] = $step;

            return;
        }

        $pulled = count($photos);
        $saved = 0;

        // Build lookup: user_id (employee_code) => user_pk
        $matchedByExtId = [];
        // Build lookup: uid (internal device ID) => user_pk
        $matchedByUid = [];
        foreach ($matched as $entry) {
            $matchedByExtId[$entry['user_id']] = (int) $entry['user_pk'];
            $matchedByUid[(int) $entry['uid']] = (int) $entry['user_pk'];
        }

        // Check if this is ZKTeco face template mode
        $isZktecoFace = ! empty($photos[0]['template_type']) && $photos[0]['template_type'] === 'zkteco-face';

        if ($isZktecoFace) {
            // ZKTeco: Store face templates in user_fingerprints table (finger_id 50-54)
            // Bulk-fetch existing face templates to avoid per-photo queries
            $userPks = array_unique(array_filter([
                ...array_values($matchedByExtId),
                ...array_values($matchedByUid),
            ]));
            $existingFaceTemplates = UserFingerprint::query()
                ->where('device_id', $device->id)
                ->whereIn('user_id', $userPks)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy(fn ($fp) => $fp->user_id.'_'.$fp->finger_id);

            foreach ($photos as $photo) {
                $employeeNo = (string) ($photo['employee_no'] ?? '');
                $templateData = $photo['photo_base64'] ?? '';
                $faceId = (int) ($photo['face_id'] ?? 50);

                if ($employeeNo === '' || $templateData === '') {
                    continue;
                }

                $userPk = $matchedByUid[(int) $employeeNo] ?? $matchedByExtId[$employeeNo] ?? null;
                if (! $userPk) {
                    continue;
                }

                try {
                    $existing = $existingFaceTemplates->get($userPk.'_'.$faceId);

                    $payload = [
                        'user_id' => $userPk,
                        'device_id' => $device->id,
                        'finger_id' => $faceId,
                        'template_data' => $templateData,
                        'template_format' => 'zkteco-face',
                        'template_version' => 9,
                        'quality' => 0,
                        'is_master' => $faceId === 50,
                        'captured_at' => now(),
                        'synced_at' => now(),
                    ];

                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        UserFingerprint::create($payload);
                    }

                    $saved++;
                } catch (\Throwable $e) {
                    Log::warning('Face template save failed', [
                        'employee_no' => $employeeNo,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // Hikvision/others: Save as JPEG files (original behavior)
            $storageDir = storage_path('app/face_photos/'.$device->serial_number);
            File::makeDirectory($storageDir, 0755, true, true);

            foreach ($photos as $photo) {
                $employeeNo = (string) ($photo['employee_no'] ?? '');
                $photoBase64 = $photo['photo_base64'] ?? null;
                $faceUrl = $photo['face_url'] ?? '';

                if ($employeeNo === '' || (! $photoBase64 && ! $faceUrl)) {
                    continue;
                }

                $userPk = $matchedByExtId[$employeeNo] ?? null;
                if (! $userPk) {
                    continue;
                }

                try {
                    if ($photoBase64) {
                        $imageData = base64_decode($photoBase64);
                        if ($imageData === false) {
                            continue;
                        }
                    } else {
                        continue;
                    }

                    $filename = $employeeNo.'.jpg';
                    $filepath = $storageDir.'/'.$filename;
                    file_put_contents($filepath, $imageData);

                    $relativePath = 'face_photos/'.$device->serial_number.'/'.$filename;
                    User::where('id', $userPk)->update(['face_photo_path' => $relativePath]);

                    $saved++;
                } catch (\Throwable $e) {
                    Log::warning('Face photo save failed', [
                        'employee_no' => $employeeNo,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $result['totals']['face_photos_pulled'] = $pulled;
        $result['totals']['face_photos_saved'] = $saved;

        $step['status'] = 'ok';
        $step['message'] = sprintf('%d pulled, %d saved', $pulled, $saved);
        $step['data'] = [
            'pulled' => $pulled,
            'saved' => $saved,
        ];
        $result['steps'][] = $step;
    }

    /**
     * Step 4 — pull attendance and persist + reconcile.
     *
     * @param  array<int, array{uid:int,user_id:string,name:string,user_pk:?int}>  $matched
     * @param  array<string, mixed>  $result
     */
    protected function stepAttendance(
        FingerprintDevice $device,
        array $matched,
        array &$result,
        bool $enabled,
        DeviceAdapterInterface $adapter,
    ): void {
        $step = [
            'name' => 'attendance',
            'status' => $enabled ? 'running' : 'skipped',
            'message' => null,
        ];

        if (! $enabled) {
            $step['message'] = 'skipped';
            $result['steps'][] = $step;

            return;
        }

        try {
            $logs = $adapter->getAttendance(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
            $logs = is_array($logs) ? $logs : [];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['errors'][] = 'attendance: '.$e->getMessage();
            $result['steps'][] = $step;

            return;
        }

        $pulled = count($logs);
        $saved = 0;
        $sessions = 0;

        $matchedByUserId = [];
        $matchedByUid = [];
        foreach ($matched as $entry) {
            $matchedByUserId[$entry['user_id']] = (int) $entry['user_pk'];
            if (! empty($entry['uid'])) {
                $matchedByUid[(int) $entry['uid']] = (int) $entry['user_pk'];
            }
        }

        // Pre-build a set of parsed timestamps to avoid re-parsing in duplicate check
        $parsedLogs = [];
        foreach ($logs as $log) {
            $externalId = trim((string) ($log['user_id'] ?? ''));
            $uid = (int) ($log['uid'] ?? 0);
            $stamp = $this->parseTimestamp($log['timestamp'] ?? null);
            if (! $stamp || ($externalId === '' && $uid === 0)) {
                continue;
            }
            $parsedLogs[] = ['log' => $log, 'external_id' => $externalId, 'stamp' => $stamp];
        }

        // Bulk-fetch existing raw logs for this device to avoid per-log duplicate queries
        $existingKeys = [];
        if (! empty($parsedLogs)) {
            $deviceUserIdToLogs = [];
            foreach ($parsedLogs as $pl) {
                $deviceUserIdToLogs[$pl['external_id']][] = $pl['stamp']->format('Y-m-d H:i:s');
            }
            // Query in batches of 100 to avoid massive IN clauses
            foreach (array_chunk(array_keys($deviceUserIdToLogs), 100) as $chunk) {
                $existing = RawAttendanceLog::query()
                    ->where('device_id', $device->id)
                    ->whereIn('device_user_id', $chunk)
                    ->select('device_user_id', 'punch_time')
                    ->get();
                foreach ($existing as $row) {
                    $existingKeys[$row->device_user_id.'_'.$row->punch_time] = true;
                }
            }
        }

        DB::transaction(function () use ($parsedLogs, $device, $matchedByUserId, $matchedByUid, &$saved, &$sessions, &$existingKeys) {
            foreach ($parsedLogs as $pl) {
                $log = $pl['log'];
                $externalId = $pl['external_id'];
                $stamp = $pl['stamp'];

                $userPk = $matchedByUserId[$externalId] ?? $matchedByUid[(int) ($log['uid'] ?? 0)] ?? null;
                $punchType = $this->resolvePunchType($log);

                // Check pre-fetched duplicate set
                $key = $externalId.'_'.$stamp->format('Y-m-d H:i:s');
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $raw = RawAttendanceLog::create([
                    'user_id' => $userPk,
                    'device_id' => $device->id,
                    'device_user_id' => $externalId,
                    'punch_time' => $stamp,
                    'punch_type' => $punchType,
                    'verify_type' => (int) ($log['punch'] ?? 0),
                    'work_code' => (int) ($log['status'] ?? 0),
                    'source' => 'device_pull',
                    'processed' => false,
                    'ip_address' => $device->ip_address,
                    'raw_data' => $log,
                ]);

                $existingKeys[$key] = true;
                $saved++;

                if ($userPk && $session = $this->rawLogService->processLog($raw)) {
                    $sessions++;
                }
            }
        });

        // Post-sync: resolve any remaining NULL user_id by matching
        // device_user_id against employee_code, then process them.
        $resolved = $this->resolveAndProcessUnresolvedLogs($device);

        if ($saved > 0) {
            $device->update(['attendance_log_count' => $saved]);
        }

        $result['totals']['attendance_pulled'] = $pulled;
        $result['totals']['attendance_saved'] = $saved;
        $result['totals']['attendance_sessions'] = $sessions + ($resolved['sessions'] ?? 0);
        $result['totals']['attendance_resolved'] = $resolved['resolved'] ?? 0;

        $step['status'] = 'ok';
        $step['message'] = sprintf(
            '%d pulled, %d saved, %d sessions (%d auto-resolved)',
            $pulled,
            $saved,
            $sessions + ($resolved['sessions'] ?? 0),
            $resolved['resolved'] ?? 0
        );
        $step['data'] = [
            'pulled' => $pulled,
            'saved' => $saved,
            'sessions_created' => $sessions,
            'auto_resolved' => $resolved['resolved'] ?? 0,
            'auto_sessions' => $resolved['sessions'] ?? 0,
        ];
        $result['steps'][] = $step;
    }

    /**
     * Post-sync step: resolve NULL user_id in raw logs by matching
     * device_user_id → employee_code, then process them into sessions.
     *
     * @return array{resolved: int, sessions: int}
     */
    protected function resolveAndProcessUnresolvedLogs(FingerprintDevice $device): array
    {
        $resolved = 0;
        $sessions = 0;

        // Find all raw logs for this device that have user_id = NULL
        $unresolvedLogs = RawAttendanceLog::query()
            ->where('device_id', $device->id)
            ->whereNull('user_id')
            ->where('processed', false)
            ->orderBy('punch_time')
            ->get();

        if ($unresolvedLogs->isEmpty()) {
            return ['resolved' => 0, 'sessions' => 0];
        }

        // Build a lookup: device_user_id (employee_code) → user.id
        $employeeCodes = $unresolvedLogs->pluck('device_user_id')
            ->filter(fn ($code) => $code !== '' && $code !== null)
            ->unique()
            ->values()
            ->all();

        if (empty($employeeCodes)) {
            return ['resolved' => 0, 'sessions' => 0];
        }

        $lowerCodes = array_map('strtolower', $employeeCodes);
        $placeholders = implode(',', array_fill(0, count($lowerCodes), '?'));
        $userMap = User::query()
            ->whereRaw("LOWER(employee_code) IN ($placeholders)", $lowerCodes)
            ->get()
            ->keyBy(fn ($u) => strtolower((string) $u->employee_code));

        foreach ($unresolvedLogs as $log) {
            $code = strtolower(trim((string) $log->device_user_id));
            if ($code === '' || ! isset($userMap[$code])) {
                continue;
            }

            $log->update(['user_id' => $userMap[$code]->id]);
            $resolved++;

            if ($session = $this->rawLogService->processLog($log)) {
                $sessions++;
            }
        }

        Log::info('DeviceFullSync: auto-resolved unresolved logs', [
            'device_id' => $device->id,
            'resolved' => $resolved,
            'sessions' => $sessions,
        ]);

        return ['resolved' => $resolved, 'sessions' => $sessions];
    }

    protected function resolvePunchType(array $log): string
    {
        $punch = $log['punch'] ?? null;

        if ($punch !== null && is_numeric($punch)) {
            return ((int) $punch) === 1 ? 'check_out' : 'check_in';
        }

        return 'check_in';
    }

    protected function parseTimestamp(mixed $raw): ?DateTimeImmutable
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
