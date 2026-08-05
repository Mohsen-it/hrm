<?php

namespace Tests\Feature\Modules\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Attendance\Http\Controllers\ReportsController;
use Tests\TestCase;

/**
 * Feature coverage for {@see ReportsController}.
 */
class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The reports landing page renders.
     */
    public function test_index_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.index'))->assertOk();
    }

    /**
     * The monthly report page renders.
     */
    public function test_monthly_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.monthly'))->assertOk();
    }

    /**
     * The yearly report page renders.
     */
    public function test_yearly_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.yearly'))->assertOk();
    }

    /**
     * The per-user report page renders.
     */
    public function test_user_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.user', ['user' => 1]))->assertOk();
    }

    /**
     * The employee selector for monthly reports renders.
     */
    public function test_user_report_index_renders(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.user.index'))->assertOk();
    }

    /**
     * The schedule-window-aware employee log can be exported to Excel.
     */
    public function test_monthly_employee_log_exports(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('attendance.reports.user.monthly-log.export', [
            'user' => 1,
            'year' => 2026,
            'month' => 8,
        ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
