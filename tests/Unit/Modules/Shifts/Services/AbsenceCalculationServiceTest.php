<?php

namespace Tests\Unit\Modules\Shifts\Services;

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Models\TimeSchedule;
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

    /**
     * An official holiday excuses only the employees it actually covers: the
     * employee's rotation must not work on holidays AND the holiday must apply
     * to their branch/department — never a blanket "any holiday cancels all
     * absence" rule (mirrors the daily report / operational snapshot).
     */
    public function test_holiday_excuses_only_covered_employees(): void
    {
        $covered = $this->makeEmployee('EMP10005');
        $uncovered = $this->makeEmployee('EMP10006');
        $worksOnHolidays = $this->makeEmployee('EMP10007');

        $branchA = Branch::create([
            'company_id' => $covered->company_id,
            'branch_code' => 'BR_A',
            'branch_name' => 'Branch A',
            'status' => 1,
        ]);
        $branchB = Branch::create([
            'company_id' => $uncovered->company_id,
            'branch_code' => 'BR_B',
            'branch_name' => 'Branch B',
            'status' => 1,
        ]);

        $covered->branch_id = $branchA->id;
        $covered->save();

        $uncovered->branch_id = $branchB->id;
        $uncovered->save();

        $worksOnHolidays->branch_id = $branchA->id;
        $worksOnHolidays->save();

        $this->assignRotation($covered);
        $this->assignRotation($uncovered);
        $this->assignRotation($worksOnHolidays, workOnHolidays: true);

        Holiday::create([
            'name_ar' => 'عطلة تجريبية',
            'is_recurring' => false,
            'date' => '2026-08-06',
            'is_active' => true,
            'duration_days' => 1,
            'applies_to_all' => false,
            'applies_to_branches' => [$branchA->id],
        ]);

        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));

        $this->assertNotContains($covered->id, $absent, 'Employee on a covered branch must be excused by the holiday.');
        $this->assertContains($uncovered->id, $absent, 'Employee on an uncovered branch must stay absent.');
        $this->assertContains($worksOnHolidays->id, $absent, 'A rotation that works on holidays must not be excused.');
    }

    /**
     * Raw device punches are stored in UTC; the monthly report must group them
     * under their local app-timezone date. A 21:30 UTC punch is 00:30 the next
     * day in Asia/Riyadh and must count as present for that next day.
     */
    public function test_monthly_report_groups_raw_punches_by_local_date(): void
    {
        $user = $this->makeEmployee('EMP10008');
        $this->assignRotation($user);

        // 2026-08-06 21:30 UTC = 2026-08-07 00:30 Asia/Riyadh.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-06 21:30:00',
            'punch_type' => 'check_in',
            'source' => 'device',
            'processed' => true,
        ]);

        $report = $this->service->getMonthlyAbsenceReport(
            Carbon::parse('2026-08-06'),
            Carbon::parse('2026-08-07'),
        );

        $employee = $report['employees']->firstWhere('employee_id', $user->id);

        $this->assertNotNull($employee, 'The employee must appear in the monthly report.');
        $this->assertSame(['2026-08-06'], $employee['absent_dates'], 'The UTC punch must not be attributed to 2026-08-06.');
        $this->assertSame(1, $employee['present'], 'The punch must count as present on 2026-08-07 (its local date).');
    }

    /**
     * An employee with an open session (checked in, never checked out) must
     * never be reported absent — the daily report classifies them as a
     * missing check-out (incomplete) instead.
     */
    public function test_employee_with_open_session_is_not_absent(): void
    {
        $user = $this->makeEmployee('EMP10009');
        $this->assignRotation($user); // works every day

        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 08:00:00',
            'check_out_at' => null,
        ]);

        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));

        $this->assertNotContains($user->id, $absent, 'An employee with an open session must not be reported absent.');
    }

    /**
     * The monthly absence log must mark the day after an open session as a
     * missing check-out (incomplete), not as absence — matching the daily
     * report.
     */
    public function test_monthly_absence_marks_day_after_open_session_as_incomplete(): void
    {
        $user = $this->makeEmployee('EMP10010');
        $this->assignRotation($user);

        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 08:00:00',
            'check_out_at' => null,
        ]);

        $days = $this->service->getMonthlyAbsence($user->id, 8, 2026);

        $aug6 = collect($days)->firstWhere('date', '2026-08-06');
        $this->assertNotNull($aug6, '2026-08-06 must appear in the monthly absence days.');
        $this->assertSame('incomplete', $aug6['status'], 'The day after an open session is a missing check-out, not absence.');
    }

    /**
     * The monthly report must not add the days after an open session to the
     * absent dates — they are flagged as missing check-out (incomplete),
     * exactly like the daily report.
     */
    public function test_monthly_report_marks_day_after_open_session_as_incomplete(): void
    {
        $user = $this->makeEmployee('EMP10011');
        $this->assignRotation($user);

        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 08:00:00',
            'check_out_at' => null,
        ]);

        $report = $this->service->getMonthlyAbsenceReport(
            Carbon::parse('2026-08-06'),
            Carbon::parse('2026-08-06'),
        );

        $employee = $report['employees']->firstWhere('employee_id', $user->id);

        $this->assertNotNull($employee, 'The employee must appear in the monthly report.');
        $this->assertSame([], $employee['absent_dates'], 'The day after an open session must not count as absent.');
        $this->assertSame('incomplete', $employee['day_details'][0]['status'] ?? null);
    }

    /**
     * An open session on a rest day must NOT block absence: the daily report
     * only converts the following days into a missing check-out when the open
     * session's own day was an expected work day.
     */
    public function test_open_session_on_rest_day_does_not_block_absence(): void
    {
        $user = $this->makeEmployee('EMP10012');

        // Anchor 2026-08-01: work / rest / rest / rest repeating. 08-02 is a
        // rest day, 08-05 is a work day.
        $rotation = Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rotation '.$user->employee_code,
            'anchor_start_date' => '2026-08-01',
            'pattern' => [1, 0, 0, 0],
            'cycle_length' => 4,
            'work_days_count' => 1,
            'rest_days_count' => 3,
            'number_of_groups' => 1,
            'grace_minutes' => 0,
        ]);
        $group = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
            'start_date' => '2026-08-01',
        ]);
        RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);

        // Open session on the rest day 08-02.
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-02',
            'check_in_at' => '2026-08-02 08:00:00',
            'check_out_at' => null,
        ]);

        // 08-05 is a work day with no punch → must stay absent.
        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-05'));

        $this->assertContains($user->id, $absent, 'An open session on a rest day must not block absence.');
    }

    /**
     * An open session whose day had no assignment must NOT block absence
     * (mirrors the daily report's historical-assignment lookup).
     */
    public function test_open_session_without_assignment_on_its_day_does_not_block_absence(): void
    {
        $user = $this->makeEmployee('EMP10013');
        $this->assignRotation($user, from: '2026-08-06');

        // Open session on 08-05 — before the assignment started.
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 14:59:00',
            'check_out_at' => null,
        ]);

        // 08-08 is a work day with no punch → must stay absent.
        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-08'));

        $this->assertContains($user->id, $absent, 'An open session without an assignment on its day must not block absence.');
    }

    /**
     * The monthly report must apply the same per-day rule as the daily report:
     * an open session on a rest day does not convert the following work day
     * into "incomplete" — it stays absent.
     */
    public function test_monthly_report_open_session_on_rest_day_stays_absent(): void
    {
        $user = $this->makeEmployee('EMP10014');

        // Anchor 2026-08-01: work / rest / rest / rest. 08-02 rest, 08-05 work.
        $rotation = Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rotation '.$user->employee_code,
            'anchor_start_date' => '2026-08-01',
            'pattern' => [1, 0, 0, 0],
            'cycle_length' => 4,
            'work_days_count' => 1,
            'rest_days_count' => 3,
            'number_of_groups' => 1,
            'grace_minutes' => 0,
        ]);
        $group = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
            'start_date' => '2026-08-01',
        ]);
        RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);

        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-02', // rest day
            'check_in_at' => '2026-08-02 08:00:00',
            'check_out_at' => null,
        ]);

        $report = $this->service->getMonthlyAbsenceReport(
            Carbon::parse('2026-08-05'),
            Carbon::parse('2026-08-05'),
        );

        $employee = $report['employees']->firstWhere('employee_id', $user->id);

        $this->assertNotNull($employee, 'The employee must appear in the monthly report.');
        $this->assertSame(['2026-08-05'], $employee['absent_dates'], 'A rest-day open session must not convert 08-05 into incomplete.');
    }

    /**
     * The monthly report's expected check-in/out columns must come from the
     * assignment snapshot (via RotationEngine::resolveTimes()) — the same
     * source the punch classifier uses — not from the live time schedule.
     */
    public function test_monthly_report_expected_times_come_from_assignment_snapshot(): void
    {
        $user = $this->makeEmployee('EMP10004');
        $assignment = $this->assignRotation($user);

        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Snapshot Schedule',
            'in_time' => '09:30',
            'out_time' => '18:30',
        ]);

        $assignment->snapshot_data = [
            'rotation' => ['id' => $assignment->rotation_id],
            'group' => ['id' => $assignment->rotation_group_id],
            'time_schedule' => [
                'id' => $schedule->id,
                'name' => 'Snapshot Schedule',
                'in_time' => '09:30',
                'out_time' => '18:30',
                'is_multi_day' => false,
                'late_margin' => 0,
                'early_margin' => 0,
                'in_ahead_margin' => 0,
                'in_above_margin' => 0,
                'out_ahead_margin' => 0,
                'out_above_margin' => 0,
                'breaks' => [],
            ],
        ];
        $assignment->save();

        $report = $this->service->getMonthlyAbsenceReport(
            Carbon::parse('2026-08-06'),
            Carbon::parse('2026-08-06'),
        );

        $employee = $report['employees']->firstWhere('employee_id', $user->id);

        $this->assertNotNull($employee, 'The employee must appear in the monthly report.');
        $this->assertSame('09:30', $employee['expected_in']);
        $this->assertSame('18:30', $employee['expected_out']);
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

    private function assignRotation(User $user, bool $workOnHolidays = false, string $from = '2026-08-01'): RotationAssignment
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
            'work_on_holidays' => $workOnHolidays,
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
            'start_date' => $from,
            'end_date' => null,
        ]);
    }
}
