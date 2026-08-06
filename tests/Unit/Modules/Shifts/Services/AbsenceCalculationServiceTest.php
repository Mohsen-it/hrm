<?php

namespace Tests\Unit\Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Users\Models\User;
use Tests\TestCase;

class AbsenceCalculationServiceTest extends TestCase
{
    private AbsenceCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AbsenceCalculationService::class);
    }

    /**
     * A device punch must clear the employee from the absent list even when
     * the session pipeline could not create an AttendanceSession for that date
     * (e.g. an early-morning punch outside the configured check-in window that
     * was attached to a previous day's still-open session).
     */
    public function test_employee_with_a_raw_punch_but_no_session_is_not_absent(): void
    {
        $user = $this->makeEmployee('EMP10001');
        $this->assignRotation($user);

        // The device punch happened on 2026-08-06 (06:05 Asia/Riyadh = 03:05 UTC)
        // but no AttendanceSession was ever created for that date.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-06 03:05:04',
            'punch_type' => 'check_out',
            'source' => 'device',
            'processed' => true,
        ]);

        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));

        $this->assertContains($user->id, $this->service->getExpectedEmployees(Carbon::parse('2026-08-06')));
        $this->assertNotContains($user->id, $absent, 'Employee with a raw punch must not be reported absent.');
    }

    public function test_employee_without_any_punch_is_absent(): void
    {
        $user = $this->makeEmployee('EMP10002');
        $this->assignRotation($user);

        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));

        $this->assertContains($user->id, $absent);
    }

    /**
     * The monthly absence must also count a raw punch as a present day.
     */
    public function test_monthly_absence_counts_raw_punch_day_as_present(): void
    {
        $user = $this->makeEmployee('EMP10003');
        $this->assignRotation($user);

        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-06 03:05:04',
            'punch_type' => 'check_in',
            'source' => 'device',
            'processed' => true,
        ]);

        $days = $this->service->getMonthlyAbsence($user->id, 8, 2026);

        $aug6 = collect($days)->firstWhere('date', '2026-08-06');
        $this->assertNotNull($aug6, '2026-08-06 must appear in the monthly absence days.');
        $this->assertSame('present', $aug6['status']);
    }

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
