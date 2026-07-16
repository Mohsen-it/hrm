<?php

namespace Modules\AttendanceIntegration\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceAdapterInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\DTOs\SyncResult;
use Modules\AttendanceIntegration\Events\DeviceSyncCompleted;
use Modules\AttendanceIntegration\Models\DeviceAdapter;
use Modules\FingerprintDevices\Models\FingerprintDevice;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Users\Models\User;

class DeviceSyncOrchestrator
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private DeviceAdapterResolver $adapterResolver,
    ) {}

    public function run(AttendanceDeviceInterface $device, array $options = [], ?callable $onProgress = null): SyncResult
    {
        $options = array_merge([
            'info' => true,
            'users' => true,
            'fingerprints' => true,
            'attendance' => true,
        ], $options);

        $startedAt = microtime(true);
        $startedAtStr = now()->toDateTimeString();
        $driver = $device->getDriverName();
        $adapter = $this->adapterResolver->getAdapter($driver);
        $rawDevice = $this->resolveRawDevice($device);

        $steps = [];
        $totals = [
            'users_on_device' => 0,
            'users_matched' => 0,
            'users_unmatched' => 0,
            'fingerprints_pulled' => 0,
            'fingerprints_saved' => 0,
            'attendance_pulled' => 0,
            'attendance_saved' => 0,
            'attendance_sessions' => 0,
        ];
        $errors = [];

        try {
            $this->emitProgress($onProgress, 'info', 'running', 'Pulling device info...', 0);
            $infoResult = $this->stepInfo($device, $adapter, $options['info']);
            $steps[] = $infoResult['step'];
            if (isset($infoResult['info'])) {
                $totals['users_on_device'] = $infoResult['info']->userCount;
            }

            $this->emitProgress($onProgress, 'users', 'running', 'Syncing users...', 25);
            $usersResult = $this->stepUsers($device, $adapter, $options['users']);
            $steps[] = $usersResult['step'];
            $matched = $usersResult['matched'] ?? [];
            $unmatchedUsers = $usersResult['unmatched'] ?? [];
            $totals['users_matched'] = count($matched);
            $totals['users_unmatched'] = count($unmatchedUsers);

            $this->emitProgress($onProgress, 'fingerprints', 'running', 'Pulling fingerprints...', 50);
            $fpResult = $this->stepFingerprints($rawDevice, $adapter, $matched, $options['fingerprints']);
            $steps[] = $fpResult['step'];
            $totals['fingerprints_pulled'] = $fpResult['pulled'] ?? 0;
            $totals['fingerprints_saved'] = $fpResult['saved'] ?? 0;

            $this->emitProgress($onProgress, 'attendance', 'running', 'Pulling attendance logs...', 75);
            $attResult = $this->stepAttendance($rawDevice, $adapter, $matched, $options['attendance'], $driver);
            $steps[] = $attResult['step'];
            $totals['attendance_pulled'] = $attResult['pulled'] ?? 0;
            $totals['attendance_saved'] = $attResult['saved'] ?? 0;
            $totals['attendance_sessions'] = $attResult['sessions'] ?? 0;

            $this->deviceRepository->updateSyncTimestamp($device);
        } catch (\Throwable $e) {
            Log::error('DeviceSyncOrchestrator failed', [
                'device_id' => $device->getId(),
                'error' => $e->getMessage(),
            ]);
            $errors[] = $e->getMessage();
        }

        $duration = round(microtime(true) - $startedAt, 2);

        $result = new SyncResult(
            device_id: $device->getId(),
            device_name: $device->getName(),
            serial_number: (string) $device->getSerialNumber(),
            steps: $steps,
            totals: $totals,
            errors: $errors,
            durationSeconds: $duration,
            startedAt: $startedAtStr,
            finishedAt: now()->toDateTimeString(),
        );

        Event::dispatch(new DeviceSyncCompleted($device, $result));

        if ($onProgress) {
            $onProgress('done', 'ok', 'Sync completed', 100, $totals);
        }

        return $result;
    }

    private function resolveRawDevice(AttendanceDeviceInterface $device): FingerprintDevice
    {
        if ($device instanceof DeviceAdapter) {
            return $device->getRawModel();
        }

        return FingerprintDevice::findOrFail($device->getId());
    }

    private function emitProgress(?callable $onProgress, string $step, string $status, string $message, int $percent): void
    {
        if ($onProgress) {
            $onProgress($step, $status, $message, $percent, []);
        }
    }

    private function stepInfo(AttendanceDeviceInterface $device, DeviceAdapterInterface $adapter, bool $enabled): array
    {
        $step = ['name' => 'info', 'status' => 'skipped', 'message' => null];

        if (! $enabled) {
            return ['step' => $step, 'info' => null];
        }

        try {
            $info = $adapter->getDeviceInfo(
                $device->getIpAddress(),
                $device->getPort(),
                $device->getCommKey(),
                $device->getTimeout(),
            );

            $rawDevice = $this->resolveRawDevice($device);
            if ($info) {
                $rawDevice->update([
                    'user_count' => $info->userCount,
                    'fingerprint_count' => $info->fingerprintCount,
                    'attendance_log_count' => $info->attendanceCount,
                ]);
                if ($info->serialNumber) {
                    $rawDevice->update(['serial_number' => $info->serialNumber]);
                }
                $rawDevice->refresh();
            }

            $step['status'] = 'ok';
            $step['message'] = 'Device info refreshed';

            return ['step' => $step, 'info' => $info];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();

            return ['step' => $step, 'info' => null];
        }
    }

    private function stepUsers(AttendanceDeviceInterface $device, DeviceAdapterInterface $adapter, bool $enabled): array
    {
        $step = ['name' => 'users', 'status' => 'skipped', 'message' => null];

        if (! $enabled) {
            return ['step' => $step, 'matched' => [], 'unmatched' => []];
        }

        try {
            $deviceUsers = $adapter->getUsers(
                $device->getIpAddress(),
                $device->getPort(),
                $device->getCommKey(),
                $device->getTimeout(),
            );

            $matched = [];
            $unmatched = [];
            $created = 0;

            foreach ($deviceUsers as $du) {
                $externalId = trim((string) ($du['user_id'] ?? ''));
                if ($externalId === '') {
                    $unmatched[] = ['uid' => $du['uid'] ?? 0, 'user_id' => $externalId, 'name' => $du['name'] ?? '', 'reason' => 'empty user_id'];

                    continue;
                }

                $user = User::query()->where('employee_code', $externalId)->first();

                if (! $user && ! empty($du['name'])) {
                    $user = User::query()->where('full_name_ar', $du['name'])->orWhere('name', $du['name'])->first();
                }

                if (! $user) {
                    $autoName = ! empty($du['name']) ? $du['name'] : 'User '.$externalId;
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
                    'uid' => (int) ($du['uid'] ?? 0),
                    'user_id' => $externalId,
                    'name' => (string) ($du['name'] ?? ''),
                    'user_pk' => (int) $user->id,
                ];
            }

            $step['status'] = 'ok';
            $step['message'] = sprintf('%d matched, %d created, %d unmatched', count($matched), $created, count($unmatched));

            return ['step' => $step, 'matched' => $matched, 'unmatched' => $unmatched];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();

            return ['step' => $step, 'matched' => [], 'unmatched' => []];
        }
    }

    private function stepFingerprints(FingerprintDevice $device, DeviceAdapterInterface $adapter, array $matched, bool $enabled): array
    {
        $step = ['name' => 'fingerprints', 'status' => $enabled ? 'running' : 'skipped', 'message' => null];

        if (! $enabled || empty($matched)) {
            $step['status'] = 'ok';
            $step['message'] = 'skipped or no matched users';

            return ['step' => $step, 'pulled' => 0, 'saved' => 0];
        }

        $pulled = 0;
        $saved = 0;

        try {
            foreach ($matched as $entry) {
                $userPk = (int) $entry['user_pk'];
                $uid = (int) $entry['uid'];

                try {
                    $templates = $adapter->getFingerprintTemplates(
                        $device->ip_address,
                        $device->port,
                        (string) $device->comm_key,
                        (int) ($device->timeout ?? 30),
                        $uid,
                    );
                } catch (\Throwable $e) {
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
                        'template_format' => 'device-native',
                        'template_version' => 9,
                        'quality' => 0,
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

            $step['status'] = 'ok';
            $step['message'] = sprintf('%d pulled, %d saved', $pulled, $saved);

            return ['step' => $step, 'pulled' => $pulled, 'saved' => $saved];
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();

            return ['step' => $step, 'pulled' => $pulled, 'saved' => $saved];
        }
    }

    private function stepAttendance(FingerprintDevice $device, DeviceAdapterInterface $adapter, array $matched, bool $enabled, string $driver = 'zkteco'): array
    {
        $step = ['name' => 'attendance', 'status' => $enabled ? 'running' : 'skipped', 'message' => null];

        if (! $enabled) {
            $step['message'] = 'skipped';

            return ['step' => $step, 'pulled' => 0, 'saved' => 0, 'sessions' => 0];
        }

        try {
            $logs = $adapter->getAttendance(
                $device->ip_address,
                $device->port,
                (string) $device->comm_key,
                (int) ($device->timeout ?? 30),
            );
        } catch (\Throwable $e) {
            $step['status'] = 'failed';
            $step['message'] = $e->getMessage();

            return ['step' => $step, 'pulled' => 0, 'saved' => 0, 'sessions' => 0];
        }

        $pulled = count($logs);
        $saved = 0;
        $sessions = 0;

        $matchedByUserId = [];
        foreach ($matched as $entry) {
            $matchedByUserId[$entry['user_id']] = (int) $entry['user_pk'];
        }

        $normalizer = $this->adapterResolver->getNormalizer($driver);

        DB::transaction(function () use ($logs, $device, $matchedByUserId, $normalizer, &$saved, &$sessions) {
            foreach ($logs as $log) {
                $normalized = $normalizer->normalize($log);
                $externalId = $normalized->deviceUserId;
                if ($externalId === '') {
                    continue;
                }

                $userPk = $matchedByUserId[$externalId] ?? null;
                $punchType = $normalized->punchType->value;

                $raw = RawAttendanceLog::create([
                    'user_id' => $userPk,
                    'device_id' => $device->id,
                    'device_user_id' => $externalId,
                    'punch_time' => $normalized->timestamp,
                    'punch_type' => in_array($punchType, ['check_in', 'check_out']) ? $punchType : 'unknown',
                    'verify_type' => $normalized->verifyMethod->value,
                    'work_code' => $normalized->workCode,
                    'source' => 'device_pull',
                    'processed' => false,
                    'ip_address' => $device->ip_address,
                    'raw_data' => $normalized->rawData,
                ]);

                $saved++;

                if ($userPk && $session = $this->reconcileSession($raw, $userPk, $device, $normalized->timestamp, $punchType)) {
                    $sessions++;
                }
            }
        });

        if ($saved > 0) {
            $device->update(['attendance_log_count' => $saved]);
        }

        $step['status'] = 'ok';
        $step['message'] = sprintf('%d pulled, %d saved, %d sessions', $pulled, $saved, $sessions);

        return ['step' => $step, 'pulled' => $pulled, 'saved' => $saved, 'sessions' => $sessions];
    }

    private function reconcileSession(RawAttendanceLog $raw, int $userPk, FingerprintDevice $device, \DateTimeImmutable $stamp, string $punchType): ?AttendanceSession
    {
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
            Log::warning('DeviceSyncOrchestrator::reconcileSession failed', [
                'user_id' => $userPk,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
