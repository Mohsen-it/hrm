<?php

namespace Tests\Unit\Modules\AttendanceIntegration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_healthy_on_empty_state(): void
    {
        $this->artisan('attendance:health-check')->assertSuccessful();
    }

    public function test_fails_when_old_job_is_stuck(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'StuckJob', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time() - 3600,
            'created_at' => time() - 3600,
        ]);

        $this->artisan('attendance:health-check')->assertFailed();
    }
}
