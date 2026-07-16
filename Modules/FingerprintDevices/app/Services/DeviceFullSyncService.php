<?php

namespace Modules\FingerprintDevices\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Services\DeviceAdapterResolver;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\FingerprintDevices\Repositories\FingerprintDeviceRepository;
use Modules\FingerprintDevices\Repositories\UserFingerprintRepository;
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
    public function __construct(
        private FingerprintDeviceRepository $deviceRepository,
        private UserFingerprintRepository $fingerprintRepository,
        private DeviceAdapterResolver $adapterResolver,
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

                $user = User::query()
                    ->where('employee_code', $externalId)
                    ->first();

                if (! $user && $name !== '') {
                    $user = User::query()
                        ->where('full_name_ar', $name)
                        ->orWhere('name', $name)
                        ->first();
                }

                if (! $user) {
                    $autoName = $name !== '' ? $name : 'User '.$externalId;

                    $user = User::create([
                        'employee_code' => $externalId,
                        'name' => $autoName,
                        'full_name_ar' => $autoName,
                        'email' => 'device_'.strtolower($externalId).'@hrm.local',
                        'password' => bcrypt('password'),
                        'status' => 1,
                        'is_active_employee' => true,
                    ]);
                    $created++;
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
            foreach ($matched as $entry) {
                $userPk = (int) $entry['user_pk'];
                $uid = (int) $entry['uid'];

                if ($clearLocal) {
                    $removed += $this->fingerprintRepository->deleteForUser($userPk);
                }

                try {
                    $templates = $adapter->getFingerprintTemplates(
                        $device->ip_address,
                        $device->port,
                        (string) $device->comm_key,
                        (int) ($device->timeout ?? 30),
                        $uid,
                    );
                    $templates = is_array($templates) ? $templates : [];
                } catch (\Throwable $e) {
                    $errors[] = sprintf('user %s templates: %s', $entry['user_id'], $e->getMessage());

                    continue;
                }

                foreach ($templates as $tpl) {
                    $pulled++;

                    $templateData = (string) ($tpl['template'] ?? '');
                    if ($templateData === '') {
                        continue;
                    }

                    $fingerId = (int) ($tpl['fid'] ?? 0);

                    $existing = UserFingerprint::query()
                        ->where('device_id', $device->id)
                        ->where('user_id', $userPk)
                        ->where('finger_id', $fingerId)
                        ->first();

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

            if (! empty($errors)) {
                $step['warnings'] = $errors;
                foreach ($errors as $err) {
                    $result['errors'][] = 'fingerprints: '.$err;
                }
            }
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();
            $result['errors'][] = 'fingerprints: '.$e->getMessage();
        }

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
        foreach ($matched as $entry) {
            $matchedByUserId[$entry['user_id']] = (int) $entry['user_pk'];
        }

        DB::transaction(function () use ($logs, $device, $matchedByUserId, &$saved, &$sessions) {
            foreach ($logs as $log) {
                $externalId = trim((string) ($log['user_id'] ?? ''));
                $stamp = $this->parseTimestamp($log['timestamp'] ?? null);
                if (! $stamp || $externalId === '') {
                    continue;
                }

                $userPk = $matchedByUserId[$externalId] ?? null;

                $punchType = $this->resolvePunchType($log);

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

                $saved++;

                if ($userPk && $session = $this->reconcileSession($raw, $userPk, $device, $stamp, $punchType)) {
                    $sessions++;
                }
            }
        });

        if ($saved > 0) {
            $device->update(['attendance_log_count' => $saved]);
        }

        $result['totals']['attendance_pulled'] = $pulled;
        $result['totals']['attendance_saved'] = $saved;
        $result['totals']['attendance_sessions'] = $sessions;

        $step['status'] = 'ok';
        $step['message'] = sprintf(
            '%d pulled, %d saved, %d sessions',
            $pulled,
            $saved,
            $sessions
        );
        $step['data'] = [
            'pulled' => $pulled,
            'saved' => $saved,
            'sessions_created' => $sessions,
        ];
        $result['steps'][] = $step;
    }

    /**
     * Best-effort reconciliation: an open session becomes a check-out,
     * otherwise a check-in.
     */
    protected function reconcileSession(
        RawAttendanceLog $raw,
        int $userPk,
        FingerprintDevice $device,
        DateTimeImmutable $stamp,
        string $punchType,
    ): ?AttendanceSession {
        $open = AttendanceSession::query()
            ->where('user_id', $userPk)
            ->whereNull('check_out_at')
            ->orderByDesc('check_in_at')
            ->first();

        try {
            if ($punchType === 'check_out' && $open) {
                $open->update([
                    'check_out_at' => $stamp,
                    'raw_log_id' => $raw->id,
                    'updated_at' => now(),
                ]);
                $raw->markProcessed();

                return $open->fresh();
            }

            if ($punchType === 'check_in' && ! $open) {
                $session = AttendanceSession::create([
                    'user_id' => $userPk,
                    'device_id' => $device->id,
                    'raw_log_id' => $raw->id,
                    'attendance_date' => $stamp->format('Y-m-d'),
                    'check_in_at' => $stamp,
                    'status' => 'open',
                    'session_type' => 'normal',
                    'source' => 'device_pull',
                ]);
                $raw->markProcessed();

                return $session;
            }
        } catch (\Throwable $e) {
            Log::warning('DeviceFullSyncService::reconcileSession failed', [
                'user_id' => $userPk,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
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
