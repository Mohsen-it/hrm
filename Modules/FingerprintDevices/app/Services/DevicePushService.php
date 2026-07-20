<?php

namespace Modules\FingerprintDevices\Services;

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
                $this->emitProgress($onProgress, 'push_fingerprints', 'ok', 'تم دفع البصمات', 95, $result['totals']);
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
        foreach ($users as $user) {
            try {
                $ok = $adapter->addUser(
                    $device->ip_address,
                    $device->port,
                    (string) $device->comm_key,
                    (int) $device->timeout,
                    UserData::fromArray([
                        'uid' => 0,
                        'user_id' => $user->employee_code,
                        'name' => $user->name,
                    ]),
                );

                if ($ok) {
                    $totals['pushed_users']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $user->id, null, 'success', null);
                } else {
                    $totals['failed_users']++;
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $user->id, null, 'failed', 'adapter reported failure');
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
        $chunks = array_chunk($users->all(), 100);

        foreach ($chunks as $chunk) {
            $payload = array_map(fn ($u) => [
                'user_id' => (string) $u->employee_code,
                'name' => (string) $u->name,
                'password' => '',
                'privilege' => 0,
                'card' => 0,
            ], $chunk);

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
                $totals['pushed_users'] += $ok;
                $totals['failed_users'] += $fail;

                foreach ($chunk as $u) {
                    $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'user', $u->id, null, 'success', null);
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
        ];
        $errors = [];
        $rows = [];

        $fingerprints = UserFingerprint::query()
            ->whereIn('user_id', $userIds)
            ->where('device_id', $device->id)
            ->whereNotNull('template_data')
            ->where('template_data', '!=', '')
            ->orderByRaw('is_master DESC')
            ->orderBy('finger_id')
            ->get();

        if ($fingerprints->isEmpty()) {
            $errors[] = 'No fingerprints found for the selected users on this device.';

            return ['totals' => $totals, 'errors' => $errors];
        }

        // Build employee_code -> device_uid map (slow — full device sync)
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
            ->whereIn('id', $fingerprints->pluck('user_id')->unique())
            ->pluck('employee_code', 'id');

        // Build fingerprint payload
        $fpPayload = [];
        foreach ($fingerprints as $fp) {
            $empCode = (string) ($empCodeByDbId[$fp->user_id] ?? '');
            $uid = $userIdToUid[$empCode] ?? null;

            if (! $uid) {
                $totals['skipped_fingerprints']++;
                $errors[] = "User {$empCode} (db#{$fp->user_id}) finger {$fp->finger_id}: user not on device — run user push first";
                $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', $fp->user_id, $fp->finger_id, 'skipped', 'user not on device');

                continue;
            }

            $fpPayload[] = [
                'uid' => $uid,
                'finger_id' => (int) $fp->finger_id,
                'template_data' => (string) $fp->template_data,
            ];
        }

        if (empty($fpPayload)) {
            $this->resultRepository->createMany($rows);

            return ['totals' => $totals, 'errors' => $errors];
        }

        // ZK devices: use batch endpoint
        $driver = strtolower($device->deviceType->manufacturer ?? '');
        if (str_contains($driver, 'zkteco') || str_contains($driver, 'zk')) {
            $bridgeUrl = rtrim(config('attendanceintegration.drivers.zkteco.bridge_url'), '/');
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
                    $ok = (int) ($body['success_count'] ?? 0);
                    $fail = (int) ($body['failed_count'] ?? 0);
                    $totals['pushed_fingerprints'] += $ok;
                    $totals['failed_fingerprints'] += $fail;

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
            // Non-ZK: individual calls
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
                        $rows[] = $this->buildResultRow($syncLog->id, $device->id, 'fingerprint', null, $fp['finger_id'], 'failed', 'adapter reported failure');
                    }
                } catch (\Throwable $e) {
                    $totals['failed_fingerprints']++;
                    $errors[] = "finger {$fp['finger_id']}: ".$e->getMessage();
                }
            }
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

        DB::transaction(function () use ($retryable, $device, $adapter, &$succeeded, &$stillFailing) {
            foreach (($retryable['user'] ?? []) as $result) {
                $user = User::find($result->target_user_id);
                if (! $user) {
                    continue;
                }

                try {
                    $ok = $adapter->addUser(
                        $device->ip_address,
                        $device->port,
                        (string) $device->comm_key,
                        (int) $device->timeout,
                        UserData::fromArray([
                            'uid' => 0,
                            'user_id' => $user->employee_code,
                            'name' => $user->name,
                        ]),
                    );

                    if ($ok) {
                        $result->update(['status' => 'success', 'error_message' => null]);
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

    private function emitProgress(?callable $onProgress, string $step, string $status, string $message, int $percent, array $data = []): void
    {
        if ($onProgress) {
            $onProgress($step, $status, $message, $percent, $data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResultRow(int $syncLogId, int $deviceId, string $type, ?int $userId, ?int $fingerId, string $status, ?string $error): array
    {
        return [
            'sync_log_id' => $syncLogId,
            'device_id' => $deviceId,
            'record_type' => $type,
            'target_user_id' => $userId,
            'target_finger_id' => $fingerId,
            'device_uid' => null,
            'status' => $status,
            'error_message' => $error,
            'attempted_at' => now(),
            'retry_count' => 0,
        ];
    }
}
