<?php

namespace Tests\Unit\Modules\Attendance;

use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\DailyAttendanceSummaryService;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * The persisted daily summary must agree with the smart-absence report: a
 * raw device punch is physical proof of presence even when the session
 * pipeline could not create an AttendanceSession for that date, so such a day
 * must never be persisted as "absent" (it would otherwise inflate the daily
 * report's monthly absence counts).
 */
class DailyAttendanceSummaryServiceTest extends TestCase
{
    private DailyAttendanceSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyAttendanceSummaryService::class);
    }

    public function test_raw_punch_day_is_not_marked_absent(): void
    {
        $user = $this->makeEmployee('EMP60001');
        $this->assignRotation($user);

        // Device punch at 06:05 Asia/Riyadh = 03:05 UTC; no session created.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-06 03:05:04',
            'punch_type' => 'check_in',
            'source' => 'device',
            'processed' => true,
        ]);

        $summary = $this->service->recalculateForUserAndDate($user->id, '2026-08-06');

        $this->assertSame('present', $summary->status, 'A raw device punch must prove presence.');
        $this->assertStringContainsString('بصمة مسجلة دون جلسة', (string) $summary->notes);
    }

    public function test_day_without_any_punch_is_absent(): void
    {
        $user = $this->makeEmployee('EMP60002');
        $this->assignRotation($user);

        $summary = $this->service->recalculateForUserAndDate($user->id, '2026-08-06');

        $this->assertSame('absent', $summary->status);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeEmployee(string $code): User
    {
        $company = Company::create([
            'company_code' => 'CMP_'.$code,
            'company_name' => 'Test Company '.$code,
            'status' => 1,
        ]);

        return User::create([
            'name' => 'Employee '.$code,
            'full_name_ar' => 'موظف '.$code,
            'employee_code' => $code,
            'email' => strtolower($code).'@test.local',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_active_employee' => true,
        ]);
    }

    private function assignRotation(User $user): RotationAssignment
    {
        $rotation = Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rotation '.$user->employee_code,
            'anchor_start_date' => '2026-08-03',
            'pattern' => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            'cycle_length' => 12,
            'work_days_count' => 12,
            'rest_days_count' => 0,
            'number_of_groups' => 1,
            'grace_minutes' => 0,
        ]);

        $group = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
            'start_date' => '2026-08-03',
        ]);

        return RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);
    }
}
