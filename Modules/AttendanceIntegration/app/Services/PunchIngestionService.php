<?php

namespace Modules\AttendanceIntegration\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\RawAttendanceLogService;
use Modules\AttendanceIntegration\Contracts\AttendanceDeviceInterface;
use Modules\AttendanceIntegration\Contracts\DeviceRepositoryInterface;
use Modules\AttendanceIntegration\DTOs\NormalizedPunch;
use Modules\AttendanceIntegration\DTOs\PunchType;
use Modules\AttendanceIntegration\Events\PunchReceived;
use Modules\AttendanceIntegration\Exceptions\DuplicatePunchException;
use Modules\Users\Models\User;

class PunchIngestionService
{
    public function __construct(
        private DeviceRepositoryInterface $deviceRepository,
        private AttendanceSessionService $attendanceSessionService,
        private RawAttendanceLogService $rawLogService,
        private AuditLogger $auditLogger,
    ) {}

    public function ingest(?AttendanceDeviceInterface $device, NormalizedPunch $punch, string $correlationId = ''): ?AttendanceSession
    {
        return DB::transaction(function () use ($device, $punch, $correlationId) {
            $deviceId = $device?->getId();
            $deviceSerial = $device?->getSerialNumber() ?? 'unknown';
            $deviceIp = $device?->getIpAddress() ?? '0.0.0.0';

            $user = $this->resolveUser($punch->deviceUserId);

            if (! $user) {
                Log::channel('attendance_push')->warning('punch_ingestion_user_not_found', [
                    'device_id' => $deviceId,
                    'device_serial' => $deviceSerial,
                    'device_user_id' => $punch->deviceUserId,
                ]);

                $this->auditLogger->logPunchSkipped(
                    $deviceId ?? 0,
                    $deviceSerial,
                    $punch->deviceUserId,
                    'user_not_found',
                    $correlationId
                );

                return null;
            }

            $punchTypeStr = $punch->punchType === PunchType::CheckIn ? 'check_in'
                : ($punch->punchType === PunchType::CheckOut ? 'check_out' : 'unknown');

            $this->guardAgainstDuplicate($deviceId ?? 0, $punch->deviceUserId, $punch->timestamp, $punchTypeStr);

            $rawLog = $this->rawLogService->createLog([
                'user_id' => $user->id,
                'device_id' => $deviceId,
                'device_user_id' => $punch->deviceUserId,
                'punch_time' => $punch->timestamp->format('Y-m-d H:i:s'),
                'punch_type' => $punchTypeStr,
                'verify_type' => $punch->verifyMethod->value,
                'work_code' => $punch->workCode,
                'source' => 'device',
                'ip_address' => $deviceIp,
                'raw_data' => $punch->rawData,
                'processed' => false,
            ]);

            Log::channel('attendance_push')->debug('punch_ingestion_raw_log_created', [
                'raw_log_id' => $rawLog->id,
                'device_id' => $device->getId(),
                'user_id' => $user->id,
                'punch_type' => $punch->punchType->value,
            ]);

            $context = [
                'device_id' => $deviceId,
                'raw_log_id' => $rawLog->id,
                'source' => 'device',
                'metadata' => [
                    'driver' => $punch->rawData['_driver'] ?? 'unknown',
                    'raw_status' => $punch->rawStatus,
                    'received_at' => $punch->timestamp->format(DATE_ATOM),
                ],
            ];

            $typedAt = new DateTimeImmutable($punch->timestamp->format('Y-m-d H:i:s.u'));

            $session = match ($punch->punchType) {
                PunchType::CheckIn => $this->attendanceSessionService->checkIn($user->id, $typedAt, $context),
                PunchType::CheckOut => $this->attendanceSessionService->checkOut($user->id, $typedAt, $context),
                default => null,
            };

            if ($session === null) {
                Log::channel('attendance_push')->warning('punch_ingestion_session_not_created', [
                    'raw_log_id' => $rawLog->id,
                    'user_id' => $user->id,
                    'punch_type' => $punch->punchType->value,
                ]);

                return null;
            }

            $rawLog->markProcessed();
            if ($device) {
                $this->deviceRepository->markOnline($device);
            }

            $this->auditLogger->logPunchIngested(
                $deviceId ?? 0,
                $deviceSerial,
                $user->id,
                $punch->punchType->value,
                $correlationId
            );

            Event::dispatch(new PunchReceived($device, $user, $session, $punch));

            Log::channel('attendance_push')->info('punch_ingestion_session_created', [
                'session_id' => $session->id,
                'raw_log_id' => $rawLog->id,
                'user_id' => $user->id,
                'device_id' => $device->getId(),
                'punch_type' => $punch->punchType->value,
            ]);

            return $session->fresh(['user', 'shift']);
        });
    }

    private function guardAgainstDuplicate(int $deviceId, string $deviceUserId, DateTimeImmutable $timestamp, string $punchType): void
    {
        if ($deviceId === 0) {
            return;
        }

        $window = 5;

        $exists = RawAttendanceLog::query()
            ->where('device_id', $deviceId)
            ->where('device_user_id', $deviceUserId)
            ->where('punch_type', $punchType)
            ->whereBetween('punch_time', [
                (clone $timestamp)->modify("-{$window} seconds")->format('Y-m-d H:i:s'),
                (clone $timestamp)->modify("+{$window} seconds")->format('Y-m-d H:i:s'),
            ])
            ->exists();

        if ($exists) {
            throw new DuplicatePunchException(
                $deviceUserId,
                $timestamp->format('Y-m-d H:i:s')
            );
        }
    }

    private function resolveUser(string $deviceUserId): ?User
    {
        if ($deviceUserId === '') {
            return null;
        }

        return User::query()
            ->where('employee_code', $deviceUserId)
            ->first()
            ?? (is_numeric($deviceUserId)
                ? User::query()->where('id', (int) $deviceUserId)->first()
                : null);
    }
}
