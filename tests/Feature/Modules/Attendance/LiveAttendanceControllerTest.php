<?php

namespace Tests\Feature\Modules\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Http\Controllers\LiveAttendanceController;
use Tests\TestCase;

/**
 * Feature coverage for {@see LiveAttendanceController}.
 */
class LiveAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The live page is reachable for an authorised user.
     */
    public function test_index_renders_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.live.index'))->assertOk();
    }

    /**
     * The snapshot endpoint returns JSON.
     */
    public function test_snapshot_returns_json(): void
    {
        $this->actAsSuperAdmin();

        $response = $this->getJson(route('attendance.live.snapshot', [
            'date' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['date', 'live', 'missing', 'health']);
    }

    /**
     * The daily-scan action is gated by the edit permission.
     */
    public function test_run_daily_scan_succeeds(): void
    {
        $this->actAsSuperAdmin();

        $response = $this->post(route('attendance.live.daily-scan'), [
            'date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
    }

    /**
     * Unauthenticated visitors are redirected.
     */
    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('attendance.live.index'))->assertRedirect();
    }
}
