<?php

namespace Tests\Unit\Modules\Attendance;

use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\DailyReportService;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;
use Modules\Vacations\Models\VacationType;
use Tests\TestCase;

class DailyReportServiceTest extends TestCase
{
    private DailyReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DailyReportService::class);
    }

    /**
     * The vacations table must list only the employees who are genuinely on
     * vacation on the report day: an employee with an approved vacation who
     * still attended work (has sessions) is classified by their actual
     * attendance instead of being listed as on leave.
     */
    public function test_vacation_table_excludes_employees_who_attended_work(): void
    {
        $onVacation = $this->makeEmployee('EMP20007');
        $this->assignOpenWorkEveryDay($onVacation);

        $attendedWhileOnVacation = $this->makeEmployee('EMP20008');
        $this->assignOpenWorkEveryDay($attendedWhileOnVacation);
        $this->makeCompleteSession($attendedWhileOnVacation, '2026-08-06 08:50:00');

        $type = VacationType::create([
            'code' => 'ANNUAL',
            'name_ar' => 'إجازة سنوية',
            'name_en' => 'Annual Leave',
            'is_active' => true,
        ]);

        foreach ([$onVacation, $attendedWhileOnVacation] as $user) {
            UserVacationRequest::create([
                'user_id' => $user->id,
                'vacation_type_id' => $type->id,
                'start_date' => '2026-08-06',
                'end_date' => '2026-08-06',
                'days_count' => 1,
                'working_days_count' => 1,
                'status' => 'approved',
            ]);
        }

        $report = $this->service->build('2026-08-06', '09:00');
        $rowOnVacation = $report['rows']->firstWhere('id', $onVacation->id);
        $rowAttended = $report['rows']->firstWhere('id', $attendedWhileOnVacation->id);

        $this->assertSame('leave', $rowOnVacation['status'], 'An employee on vacation without attendance stays in the vacations table.');
        $this->assertNotSame('leave', $rowAttended['status'], 'An employee with an approved vacation who attended work must not be in the vacations table.');
        $this->assertSame('present', $rowAttended['status']);
    }

    /**
     * The vacations table must reflect vacations on the report day only:
     * an employee with an approved vacation is listed as on leave only when
     * the report day is a work day for their rotation. On one of their rest
     * days (e.g. a 1-work / 3-rest pattern) the vacation is meaningless, so
     * they stay in the rest group and never appear in the vacations table.
     */
    public function test_vacation_table_excludes_employees_on_rotation_rest_day(): void
    {
        // 1 work + 3 rest pattern anchored 2026-08-03 => 2026-08-06 is a rest day.
        $onRestDay = $this->makeEmployee('EMP30001');
        $this->assignOpenRotation($onRestDay, [1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0], '2026-08-03');

        // Same pattern anchored 2026-08-06 => 2026-08-06 is a work day.
        $onWorkDay = $this->makeEmployee('EMP30002');
        $this->assignOpenRotation($onWorkDay, [1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0], '2026-08-06');

        $type = VacationType::create([
            'code' => 'ANNUAL2',
            'name_ar' => 'إجازة سنوية',
            'name_en' => 'Annual Leave',
            'is_active' => true,
        ]);

        foreach ([$onRestDay, $onWorkDay] as $user) {
            UserVacationRequest::create([
                'user_id' => $user->id,
                'vacation_type_id' => $type->id,
                'start_date' => '2026-08-06',
                'end_date' => '2026-08-06',
                'days_count' => 1,
                'working_days_count' => 1,
                'status' => 'approved',
            ]);
        }

        $report = $this->service->build('2026-08-06', '09:00');
        $rowRest = $report['rows']->firstWhere('id', $onRestDay->id);
        $rowWork = $report['rows']->firstWhere('id', $onWorkDay->id);

        $this->assertSame('rest', $rowRest['status'], 'A vacation on a rotation rest day must not land in the vacations table.');
        $this->assertSame('leave', $rowWork['status'], 'A vacation on a rotation work day stays in the vacations table.');
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

    private function makeRotation(User $user, array $pattern, string $anchor): Rotation
    {
        return Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rotation '.$user->employee_code.' '.$anchor,
            'anchor_start_date' => $anchor,
            'pattern' => $pattern,
            'cycle_length' => 12,
            'work_days_count' => count(array_filter($pattern, fn ($value) => $value == 1)),
            'rest_days_count' => 12 - count(array_filter($pattern, fn ($value) => $value == 1)),
            'number_of_groups' => 1,
            'grace_minutes' => 0,
            'work_on_holidays' => false,
        ]);
    }

    private function makeGroup(Rotation $rotation, string $name, int $index, string $start): RotationGroup
    {
        return RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => $name,
            'group_index' => $index,
            'start_date' => $start,
        ]);
    }

    private function assignOpenWorkEveryDay(User $user): RotationAssignment
    {
        return $this->assignOpenRotation($user, array_fill(0, 12, 1), '2026-08-03');
    }

    private function assignOpenRotation(User $user, array $pattern, string $anchor): RotationAssignment
    {
        $rotation = $this->makeRotation($user, $pattern, $anchor);
        $group = $this->makeGroup($rotation, 'A', 0, $anchor);

        return RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);
    }

    private function makeCompleteSession(User $user, string $checkIn): AttendanceSession
    {
        $date = substr((string) $checkIn, 0, 10);
        $checkOut = date('Y-m-d H:i:s', strtotime((string) $checkIn) + 8 * 3600);

        return AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => 'present',
            'session_type' => 'normal',
            'source' => 'device',
        ]);
    }
}
