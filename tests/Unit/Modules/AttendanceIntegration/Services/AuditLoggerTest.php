<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use Modules\AttendanceIntegration\Services\AuditLogger;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    private AuditLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new AuditLogger;
    }

    public function test_log_creates_audit_record(): void
    {
        $log = $this->logger->log('test_action', [
            'correlation_id' => 'corr-123',
            'device_id' => 1,
            'device_serial' => 'SN001',
            'status' => 'success',
            'context' => ['key' => 'value'],
        ]);

        $this->assertDatabaseHas('attendance_integration_audit_logs', [
            'action' => 'test_action',
            'correlation_id' => 'corr-123',
            'device_id' => 1,
            'device_serial' => 'SN001',
            'status' => 'success',
        ]);
    }

    public function test_log_push_received(): void
    {
        $log = $this->logger->logPushReceived('corr-456', 'SN002', 10);

        $this->assertSame('push_received', $log->action);
        $this->assertSame('corr-456', $log->correlation_id);
        $this->assertSame('SN002', $log->device_serial);
        $this->assertSame('received', $log->status);
        $ctx = $this->getContext($log);
        $this->assertSame(10, $ctx['row_count']);
    }

    public function test_log_push_completed(): void
    {
        $log = $this->logger->logPushCompleted('corr-789', 'SN003', 8, 2, 0, 10, 150.5);

        $this->assertSame('push_completed', $log->action);
        $this->assertSame('completed', $log->status);

        $ctx = json_decode($log->getRawOriginal('context'), true);
        $this->assertSame(8, $ctx['processed'] ?? null);
    }

    public function test_log_punch_ingested(): void
    {
        $log = $this->logger->logPunchIngested(5, 'SN005', 42, 'check_in', 'corr-100');

        $this->assertSame('punch_ingested', $log->action);
        $this->assertSame(5, $log->device_id);
        $this->assertSame('SN005', $log->device_serial);
        $this->assertSame(42, $log->user_id);
        $this->assertSame('success', $log->status);
        $this->assertSame('check_in', $this->getContext($log)['punch_type']);
    }

    public function test_log_punch_duplicate(): void
    {
        $log = $this->logger->logPunchDuplicate(5, 'SN005', 'EMP001', '2026-07-15 08:00:00', 'corr-200');

        $this->assertSame('punch_duplicate', $log->action);
        $this->assertSame('duplicate', $log->status);
        $this->assertSame('EMP001', $log->device_user_id);
        $this->assertSame('2026-07-15 08:00:00', $this->getContext($log)['punch_time']);
    }

    public function test_log_punch_skipped(): void
    {
        $log = $this->logger->logPunchSkipped(5, 'SN005', 'EMP002', 'user_not_found', 'corr-300');

        $this->assertSame('punch_skipped', $log->action);
        $this->assertSame('skipped', $log->status);
        $this->assertSame('EMP002', $log->device_user_id);
        $this->assertSame('user_not_found', $this->getContext($log)['reason']);
    }

    public function test_log_push_failed(): void
    {
        $log = $this->logger->logPushFailed('corr-400', 'SN006', 'Connection timeout');

        $this->assertSame('push_failed', $log->action);
        $this->assertSame('failed', $log->status);
        $this->assertSame('Connection timeout', $this->getContext($log)['error']);
    }

    private function getContext($log): array
    {
        $raw = $log->getRawOriginal('context');

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?? []);
    }
}
