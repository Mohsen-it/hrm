<?php

namespace Tests\Unit\Modules\Attendance;

use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\DailyReportService;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Models\TimeSchedule;
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

    /**
     * An employee who checked in but never checked out is flagged as
     * "incomplete" only once the rotation exit window (نافذة الخروج) has
     * ended, not by a hard-coded grace period.
     */
    public function test_incomplete_punch_is_flagged_when_the_rotation_exit_window_has_passed(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40001');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user)->id,
            'out_ahead_margin' => '11:00:00',
            'out_above_margin' => '11:50:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('incomplete', $row['status']);
        $this->assertStringContainsString('نافذة الخروج 11:50', $row['notes']);
    }

    /**
     * While the employee is still inside (or before) the configured exit
     * window they must not be flagged, even though their session is open.
     */
    public function test_incomplete_punch_is_not_flagged_while_inside_the_exit_window(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40002');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user)->id,
            'out_ahead_margin' => '13:00:00',
            'out_above_margin' => '14:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('present', $row['status']);
        $this->assertFalse($row['has_incomplete_punch']);
    }

    /**
     * Rotations without an absolute exit window fall back to the time-schedule
     * margin (minutes after the expected check-out), mirroring the punch
     * classification services.
     */
    public function test_incomplete_punch_uses_time_schedule_margin_when_rotation_has_no_window(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40003');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '10:00')->id,
            'out_ahead_margin' => null,
            'out_above_margin' => null,
        ]);
        // out_time 10:00 + 30 minutes margin => window ends at 10:30.
        $rotation->timeSchedule->update(['out_above_margin' => 30]);
        $this->makeOpenSession($user, '2026-08-10 08:30:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('incomplete', $row['status']);
        $this->assertStringContainsString('نافذة الخروج 10:30', $row['notes']);
    }

    /**
     * Rotations without a time schedule have no expected check-out time, but
     * the rotation still defines an absolute exit window. On a past report
     * day those employees must still be listed in the missing-checkout table:
     * they were expected to work, they came in and they never left.
     */
    public function test_incomplete_punch_flags_employees_without_time_schedule_on_past_days(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40004');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => '20:00:00',
            'out_above_margin' => '10:30:00', // overnight exit window
        ]);
        $this->makeOpenSession($user, '2026-08-09 20:15:00');

        $report = $this->service->build('2026-08-09', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
    }

    /**
     * On the current day the same rotation window applies even when the
     * rotation has no time schedule: once the absolute window has ended the
     * employee is flagged, and the note carries the window end.
     */
    public function test_incomplete_punch_uses_rotation_window_without_time_schedule_today(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40005');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:30:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 07:45:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('incomplete', $row['status']);
        $this->assertStringContainsString('نافذة الخروج 09:30', $row['notes']);
    }

    /**
     * An employee who already recorded a check-out on their main shift
     * session must not be flagged as a missing check-out just because they
     * punched in again later (e.g. stayed after an administrative shift) and
     * left that second session open. The check-out column must reflect the
     * main session too, so the report does not contradict the employee's
     * recorded check-out.
     */
    public function test_incomplete_punch_ignores_later_open_session_when_main_shift_checked_out(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40006');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);

        // Main shift: checked in 08:03 and out at 15:35 (inside the exit window).
        $this->makeCompleteSession($user, '2026-08-10 08:03:00', '15:35:00');
        // Stayed after the shift: a second visit after the exit window opens
        // a new session that stays open. This must not re-flag the employee.
        $this->makeOpenSession($user, '2026-08-10 19:09:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertSame('present', $row['status']);
        $this->assertSame('15:35', $row['check_out']);
    }

    /**
     * An open main shift session still counts as a missing check-out even
     * when a later session was completed: the scheduled exit window applies
     * to the main shift, not to an extra later visit.
     */
    public function test_incomplete_punch_still_flags_open_main_session_with_later_closed_session(): void
    {
        $this->travelTo('2026-08-10 20:00:00');

        $user = $this->makeEmployee('EMP40007');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);

        // Main shift never closed, and a later visit is completed.
        $this->makeOpenSession($user, '2026-08-10 08:03:00');
        $this->makeCompleteSession($user, '2026-08-10 19:09:00', '21:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
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

    private function makeCompleteSession(User $user, string $checkIn, ?string $checkOut = null): AttendanceSession
    {
        $date = substr((string) $checkIn, 0, 10);
        $checkOut ??= date('Y-m-d H:i:s', strtotime((string) $checkIn) + 8 * 3600);

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

    private function makeOpenSession(User $user, string $checkIn): AttendanceSession
    {
        $date = substr((string) $checkIn, 0, 10);

        return AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => $date,
            'check_in_at' => $checkIn,
            'check_out_at' => null,
            'status' => 'present',
            'session_type' => 'normal',
            'source' => 'device',
        ]);
    }

    private function makeTimeSchedule(User $user, string $inTime = '08:00', string $outTime = '17:00'): TimeSchedule
    {
        return TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Schedule '.$user->employee_code,
            'in_time' => $inTime,
            'out_time' => $outTime,
        ]);
    }
}
