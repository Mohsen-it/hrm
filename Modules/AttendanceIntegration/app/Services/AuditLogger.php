<?php

namespace Modules\AttendanceIntegration\Services;

use Modules\AttendanceIntegration\Models\AuditLog;

class AuditLogger
{
    public function log(string $action, array $data = []): AuditLog
    {
        return AuditLog::create([
            'action' => $action,
            'correlation_id' => $data['correlation_id'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'device_serial' => $data['device_serial'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'device_user_id' => $data['device_user_id'] ?? null,
            'status' => $data['status'] ?? 'info',
            'context' => $data['context'] ?? [],
            'payload_snapshot' => $data['payload_snapshot'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    public function logPushReceived(string $correlationId, string $deviceSerial, int $rowCount): AuditLog
    {
        return $this->log('push_received', [
            'correlation_id' => $correlationId,
            'device_serial' => $deviceSerial,
            'status' => 'received',
            'context' => ['row_count' => $rowCount],
            'occurred_at' => now(),
        ]);
    }

    public function logPushCompleted(string $correlationId, string $deviceSerial, int $processed, int $skipped, int $duplicates, int $rowCount, float $durationMs): AuditLog
    {
        return $this->log('push_completed', [
            'correlation_id' => $correlationId,
            'device_serial' => $deviceSerial,
            'status' => 'completed',
            'context' => [
                'processed' => $processed,
                'skipped' => $skipped,
                'duplicates' => $duplicates,
                'received' => $rowCount,
            ],
            'duration_ms' => round($durationMs, 2),
            'occurred_at' => now(),
        ]);
    }

    public function logPunchIngested(int $deviceId, string $deviceSerial, int $userId, string $punchType, string $correlationId): AuditLog
    {
        return $this->log('punch_ingested', [
            'correlation_id' => $correlationId,
            'device_id' => $deviceId,
            'device_serial' => $deviceSerial,
            'user_id' => $userId,
            'status' => 'success',
            'context' => ['punch_type' => $punchType],
            'occurred_at' => now(),
        ]);
    }

    public function logPunchDuplicate(int $deviceId, string $deviceSerial, string $deviceUserId, string $punchTime, string $correlationId): AuditLog
    {
        return $this->log('punch_duplicate', [
            'correlation_id' => $correlationId,
            'device_id' => $deviceId,
            'device_serial' => $deviceSerial,
            'device_user_id' => $deviceUserId,
            'status' => 'duplicate',
            'context' => ['punch_time' => $punchTime],
            'occurred_at' => now(),
        ]);
    }

    public function logPunchSkipped(int $deviceId, string $deviceSerial, string $deviceUserId, string $reason, string $correlationId): AuditLog
    {
        return $this->log('punch_skipped', [
            'correlation_id' => $correlationId,
            'device_id' => $deviceId,
            'device_serial' => $deviceSerial,
            'device_user_id' => $deviceUserId,
            'status' => 'skipped',
            'context' => ['reason' => $reason],
            'occurred_at' => now(),
        ]);
    }

    public function logPushFailed(string $correlationId, string $deviceSerial, string $error): AuditLog
    {
        return $this->log('push_failed', [
            'correlation_id' => $correlationId,
            'device_serial' => $deviceSerial,
            'status' => 'failed',
            'context' => ['error' => $error],
            'occurred_at' => now(),
        ]);
    }
}
