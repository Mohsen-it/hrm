<?php

namespace Tests\Unit\Modules\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Services\AttendanceMonitoringService;
use Tests\TestCase;

/**
 * Unit coverage for {@see AttendanceMonitoringService}.
 */
class AttendanceMonitoringServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service under test.
     */
    private AttendanceMonitoringService $service;

    /**
     * Initialise a fresh service for every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceMonitoringService::class);
    }

    /**
     * Health snapshot returns the expected shape even with no data.
     */
    public function test_health_snapshot_returns_expected_keys(): void
    {
        $health = $this->service->getHealthSnapshot(now()->toDateString());

        $this->assertIsArray($health);
        $this->assertArrayHasKey('live_sessions', $health);
        $this->assertArrayHasKey('missing_checkouts', $health);
        $this->assertArrayHasKey('unprocessed_raw_logs', $health);
        $this->assertArrayHasKey('anomalies', $health);
    }

    /**
     * Live sessions collection is always a Collection.
     */
    public function test_live_sessions_returns_collection(): void
    {
        $live = $this->service->getLiveSessions(now()->toDateString());

        $this->assertCount(0, $live);
    }

    /**
     * Missing checkouts collection is always a Collection.
     */
    public function test_missing_checkouts_returns_collection(): void
    {
        $missing = $this->service->getMissingCheckouts(now()->toDateString());

        $this->assertCount(0, $missing);
    }

    /**
     * Mass-lateness detection works on a date.
     */
    public function test_detect_mass_lateness_returns_array(): void
    {
        $result = $this->service->detectMassLateness(now()->toDateString(), 0.5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('late_count', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('ratio', $result);
    }

    /**
     * Mass-absence detection works on a date.
     */
    public function test_detect_mass_absence_returns_array(): void
    {
        $result = $this->service->detectMassAbsence(now()->toDateString(), 0.5);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('absent_count', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('ratio', $result);
    }
}
