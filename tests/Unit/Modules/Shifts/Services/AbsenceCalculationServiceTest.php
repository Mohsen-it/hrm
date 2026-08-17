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
use Modules\Shifts\Models\ShiftException;
use Modules\Shifts\Models\TimeSchedule;
use Modules\Shifts\Services\AbsenceCalculationService;
use Modules\Users\Models\User;
use Modules\Vacations\Models\UserVacationRequest;
use Modules\Vacations\Models\VacationType;
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
     * A missing check-out from a previous day never excuses a NEW work day's
     * absence: the employee is expected today, has no punch today and their
     * check-in deadline has passed — so they are absent today (same rule as
     * the operational snapshot / dashboard).
     */
    public function test_employee_with_open_session_from_previous_day_is_absent(): void
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

        $this->assertContains($user->id, $absent, "A previous-day open session must not excuse a new work day's absence.");
    }

    /**
     * The monthly absence log must mark the day after an open session as
     * absent — a missing check-out from the previous day is a separate, older
     * problem and never excuses the new work day.
     */
    public function test_monthly_absence_marks_day_after_open_session_as_absent(): void
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
        $this->assertSame('absent', $aug6['status'], "No punch on the new work day means absent, regardless of yesterday's open session.");
    }

    /**
     * The monthly report must add the days after an open session to the
     * absent dates — a missing check-out from the previous day never excuses
     * the new work day (same rule as getAbsentEmployees()).
     */
    public function test_monthly_report_marks_day_after_open_session_as_absent(): void
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
        $this->assertSame(['2026-08-06'], $employee['absent_dates'], 'The day after an open session must count as absent.');
        $this->assertSame('absent', $employee['day_details'][0]['status'] ?? null);
    }

    /**
     * The open-session block is strictly scoped to the previous day (اليوم
     * السابق), exactly like the daily report: a stale open session from an
     * older duty must never excuse absence on a NEW work day.
     */
    public function test_stale_open_session_does_not_block_absence_on_new_work_day(): void
    {
        $user = $this->makeEmployee('EMP10017');
        $this->assignRotation($user); // works every day

        // Open session on 08-05. Its exit window has passed long ago, so the
        // day after it (08-06) is a missing check-out — but 08-08 is a brand
        // new work day that must be judged on its own: no punch → absent.
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 08:00:00',
            'check_out_at' => null,
        ]);

        $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-08'));

        $this->assertContains($user->id, $absent, 'A stale open session from an older duty must not excuse absence on a new work day.');
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
     * A worker without a punch is only absent once their expected check-in
     * (including grace) has passed. Before that deadline they are awaiting
     * arrival — the same rule as the operational snapshot — so an early view
     * of the daily report must not flag them as absent yet.
     */
    public function test_employee_is_awaiting_arrival_before_check_in_deadline(): void
    {
        $user = $this->makeEmployee('EMP10015');
        $assignment = $this->assignRotation($user);

        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Evening Shift',
            'in_time' => '15:00',
            'out_time' => '23:00',
            'late_margin' => 30,
        ]);
        $assignment->rotation->time_schedule_id = $schedule->id;
        $assignment->rotation->save();

        // 07:00 on the report day: check-in deadline (15:00 + 30min) is ahead.
        Carbon::setTestNow(Carbon::parse('2026-08-06 07:00:00', 'Asia/Riyadh'));
        try {
            $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));
            $this->assertNotContains($user->id, $absent, 'Before the check-in deadline the employee is awaiting arrival, not absent.');
        } finally {
            Carbon::setTestNow();
        }

        // 16:00 on the report day: deadline passed, still no punch → absent.
        Carbon::setTestNow(Carbon::parse('2026-08-06 16:00:00', 'Asia/Riyadh'));
        try {
            $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));
            $this->assertContains($user->id, $absent, 'Once the check-in deadline passes without a punch the employee is absent.');
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * A rotation without any time schedule cannot define a check-in deadline,
     * so an employee on it stays "awaiting arrival" until the end of the day
     * instead of being flagged absent early (mirrors the operational snapshot).
     */
    public function test_employee_without_time_schedule_is_not_absent_before_end_of_day(): void
    {
        $user = $this->makeEmployee('EMP10016');
        $this->assignRotation($user); // no time schedule → no check-in time

        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00', 'Asia/Riyadh'));
        try {
            $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));
            $this->assertNotContains($user->id, $absent, 'Without a time schedule the employee is awaiting arrival until end of day.');
        } finally {
            Carbon::setTestNow();
        }
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

    // ------------------------------------------------------------------
    // Strong regression + invariant coverage (zero hidden-absence guarantee)
    // ------------------------------------------------------------------

    /**
     * Regression for the reported production case (عبدالقادر محمد بصطيقه):
     * an employee on a day shift who checked in YESTERDAY and never checked
     * out, then has NO punch on a NEW work day, must be reported absent once
     * today's check-in deadline has passed — a previous-day open session must
     * never excuse a new work day. Before the deadline they are awaiting
     * arrival, exactly like any other employee.
     */
    public function test_open_session_yesterday_no_punch_today_is_absent_after_deadline_and_awaiting_before(): void
    {
        $user = $this->makeEmployee('EMP10100');
        $assignment = $this->assignRotation($user);
        $this->attachSchedule($assignment, '08:00', '15:00', lateMargin: 30);

        // Yesterday: checked in 07:57, never checked out (open session).
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-05',
            'check_in_at' => '2026-08-05 07:57:00',
            'check_out_at' => null,
        ]);

        // 08:00 on the report day: check-in deadline (08:30) is still ahead.
        Carbon::setTestNow(Carbon::parse('2026-08-06 08:00:00', 'Asia/Riyadh'));
        try {
            $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));
            $this->assertNotContains($user->id, $absent, 'Before the check-in deadline the employee is awaiting arrival, not absent.');
        } finally {
            Carbon::setTestNow();
        }

        // 11:30 on the report day: deadline passed, still no punch → absent.
        Carbon::setTestNow(Carbon::parse('2026-08-06 11:30:00', 'Asia/Riyadh'));
        try {
            $absent = $this->service->getAbsentEmployees(Carbon::parse('2026-08-06'));
            $this->assertContains($user->id, $absent, 'Once the deadline passes, a previous-day open session must not excuse the absence.');
        } finally {
            Carbon::setTestNow();
        }

        // The monthly absence log must count that day as absent too.
        $days = $this->service->getMonthlyAbsence($user->id, 8, 2026);
        $aug6 = collect($days)->firstWhere('date', '2026-08-06');
        $this->assertSame('absent', $aug6['status'] ?? null, 'The monthly absence log must also mark the new work day as absent.');
    }

    /**
     * The full daily report must reconcile for every expected employee, with
     * each person in exactly one status bucket, and the absent list must match
     * the absent bucket exactly. This pins the whole classification matrix —
     * present, absent, awaiting arrival, vacation, mission, holiday (covered
     * and not covered), open-session-from-yesterday and raw-punch presence —
     * so a future rule change can never silently drop or double-count anyone.
     */
    public function test_daily_report_reconciles_every_expected_employee_and_matches_absent_list(): void
    {
        // Fixed clock 10:00: the day-shift deadline (08:30) has passed, the
        // evening-shift deadline (15:30) has not been reached yet.
        Carbon::setTestNow(Carbon::parse('2026-08-06 10:00:00', 'Asia/Riyadh'));
        try {
            $date = Carbon::parse('2026-08-06');

            $present = $this->makeEmployee('EMP10101');
            $this->withDayShift($this->assignRotation($present));
            AttendanceSession::create([
                'user_id' => $present->id,
                'attendance_date' => '2026-08-06',
                'check_in_at' => '2026-08-06 08:05:00',
                'check_out_at' => null,
            ]);

            $absentPlain = $this->makeEmployee('EMP10102');
            $this->withDayShift($this->assignRotation($absentPlain));

            $awaitingEvening = $this->makeEmployee('EMP10103');
            $this->withEveningShift($this->assignRotation($awaitingEvening));

            $onVacation = $this->makeEmployee('EMP10104');
            $this->withDayShift($this->assignRotation($onVacation));
            $vacationType = VacationType::create(['code' => 'ANNUAL', 'name_ar' => 'سنوية', 'is_active' => true]);
            UserVacationRequest::create([
                'user_id' => $onVacation->id,
                'vacation_type_id' => $vacationType->id,
                'status' => 'approved',
                'start_date' => '2026-08-05',
                'end_date' => '2026-08-07',
            ]);

            $onMission = $this->makeEmployee('EMP10105');
            $this->withDayShift($this->assignRotation($onMission));
            ShiftException::create([
                'employee_id' => $onMission->id,
                'exception_type' => 'mission',
                'from_date' => '2026-08-05',
                'to_date' => '2026-08-07',
                'status' => 'active',
            ]);

            $branch = Branch::create([
                'company_id' => $present->company_id,
                'branch_code' => 'BR_ROSTER',
                'branch_name' => 'Roster Branch',
                'status' => 1,
            ]);

            $holidayExcused = $this->makeEmployee('EMP10106');
            $this->withDayShift($this->assignRotation($holidayExcused));
            $holidayExcused->branch_id = $branch->id;
            $holidayExcused->save();

            $holidayNotExcused = $this->makeEmployee('EMP10107');
            $this->withDayShift($this->assignRotation($holidayNotExcused, workOnHolidays: true));
            $holidayNotExcused->branch_id = $branch->id;
            $holidayNotExcused->save();

            Holiday::create([
                'name_ar' => 'عطلة تجريبية',
                'is_recurring' => false,
                'date' => '2026-08-06',
                'is_active' => true,
                'duration_days' => 1,
                'applies_to_all' => false,
                'applies_to_branches' => [$branch->id],
            ]);

            $openSessionYesterday = $this->makeEmployee('EMP10108');
            $this->withDayShift($this->assignRotation($openSessionYesterday));
            AttendanceSession::create([
                'user_id' => $openSessionYesterday->id,
                'attendance_date' => '2026-08-05',
                'check_in_at' => '2026-08-05 07:57:00',
                'check_out_at' => null,
            ]);

            $openSessionButPresent = $this->makeEmployee('EMP10109');
            $this->withDayShift($this->assignRotation($openSessionButPresent));
            AttendanceSession::create([
                'user_id' => $openSessionButPresent->id,
                'attendance_date' => '2026-08-05',
                'check_in_at' => '2026-08-05 07:57:00',
                'check_out_at' => null,
            ]);
            AttendanceSession::create([
                'user_id' => $openSessionButPresent->id,
                'attendance_date' => '2026-08-06',
                'check_in_at' => '2026-08-06 08:10:00',
                'check_out_at' => null,
            ]);

            $rawPunchOnly = $this->makeEmployee('EMP10110');
            $this->withDayShift($this->assignRotation($rawPunchOnly));
            // 05:30 UTC = 08:30 Asia/Riyadh — physical proof of presence with
            // no AttendanceSession row for the day.
            RawAttendanceLog::create([
                'user_id' => $rawPunchOnly->id,
                'punch_time' => '2026-08-06 05:30:00',
                'punch_type' => 'check_in',
                'source' => 'device',
                'processed' => true,
            ]);

            // Rest-day and unassigned employees are NOT expected at all.
            $restDay = $this->makeEmployee('EMP10111');
            $this->assignRestRotation($restDay);
            $unassigned = $this->makeEmployee('EMP10112');

            $expected = $this->service->getExpectedEmployees($date);
            $this->assertSame(10, $expected->count(), 'Exactly the ten on-duty employees are expected.');
            foreach ([$present, $absentPlain, $awaitingEvening, $onVacation, $onMission, $holidayExcused, $holidayNotExcused, $openSessionYesterday, $openSessionButPresent, $rawPunchOnly] as $u) {
                $this->assertContains($u->id, $expected, $u->employee_code.' must be expected.');
            }
            foreach ([$restDay, $unassigned] as $u) {
                $this->assertNotContains($u->id, $expected, $u->employee_code.' must NOT be expected.');
            }

            $breakdown = $this->service->getDailyStatusBreakdown($date);
            $this->assertSame(3, $breakdown['present'], 'present bucket');
            $this->assertSame(3, $breakdown['absent'], 'absent bucket');
            $this->assertSame(1, $breakdown['on_vacation'], 'on_vacation bucket');
            $this->assertSame(1, $breakdown['on_exception'], 'on_exception bucket');
            $this->assertSame(1, $breakdown['holiday'], 'holiday bucket');
            $this->assertSame(1, $breakdown['awaiting_arrival'], 'awaiting_arrival bucket');
            $this->assertSame(0, $breakdown['incomplete'], 'A new work day is never incomplete.');

            // Reconciliation: every expected employee is in exactly one bucket.
            $bucketSum = $breakdown['present'] + $breakdown['absent'] + $breakdown['on_vacation']
                + $breakdown['on_exception'] + $breakdown['incomplete'] + $breakdown['holiday']
                + $breakdown['awaiting_arrival'];
            $this->assertSame($expected->count(), $bucketSum, 'Every expected employee is accounted for.');

            // The absent list must be EXACTLY the three genuinely absent
            // employees — never the awaiting, vacation, mission, holiday,
            // present, open-session-but-present, or raw-punch employees.
            $absent = $this->service->getAbsentEmployees($date);
            $this->assertEqualsCanonicalizing(
                [$absentPlain->id, $holidayNotExcused->id, $openSessionYesterday->id],
                $absent->all(),
                'The absent list must contain exactly the three genuinely absent employees.'
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Over a whole week the daily report must reconcile EVERY day: the sum of
     * all status buckets equals the expected roster, the absent bucket equals
     * the absent list, and the absent list never contains an employee who is
     * not expected. This guards against date-boundary bugs — rest days,
     * vacations ending mid-week, holidays, open sessions — the class of errors
     * that silently drop or add people on a single day.
     */
    public function test_breakdown_reconciles_and_matches_absent_list_for_every_day_of_a_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 16:00:00', 'Asia/Riyadh'));
        try {
            $roster = $this->makeWeeklyRoster();

            $from = Carbon::parse('2026-08-03');
            $absentByDay = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $from->copy()->addDays($i);
                $dayStr = $date->toDateString();

                $this->assertDailyReportReconciles($date);
                $absentByDay[$dayStr] = $this->service->getAbsentEmployees($date);
            }

            // Specific per-day expectations — the daily classifications must
            // be exactly right on each boundary:
            // 08-04 is the day after the open session → the open-session
            // employee is absent (regression guard).
            $this->assertContains($roster['openSessionEmployee']->id, $absentByDay['2026-08-04']->all());
            // 08-06 is the holiday → the covered employee is excused that day.
            $this->assertNotContains($roster['holidayEmployee']->id, $absentByDay['2026-08-06']->all());
            // Vacation employee: covered 08-03..05 → not absent; free again on
            // 08-06 → absent.
            $this->assertNotContains($roster['vacationMidWeek']->id, $absentByDay['2026-08-03']->all());
            $this->assertContains($roster['vacationMidWeek']->id, $absentByDay['2026-08-06']->all());
            // Always-present is never absent; always-absent is absent every day.
            foreach ($absentByDay as $dayStr => $absent) {
                $this->assertNotContains($roster['alwaysPresent']->id, $absent->all(), 'Present employee must never be absent on '.$dayStr);
                $this->assertContains($roster['alwaysAbsent']->id, $absent->all(), 'No-punch employee must be absent on '.$dayStr);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * The monthly report must agree with the daily classifications over the
     * same range: absent_dates are exactly the days the daily report marks
     * absent, present_days equal the number of days with a punch, and covered
     * days (vacation / holiday) are never counted as absent. The daily and
     * monthly views of the same period can therefore never contradict each
     * other.
     */
    public function test_monthly_report_agrees_with_daily_breakdown_over_the_same_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 16:00:00', 'Asia/Riyadh'));
        try {
            $roster = $this->makeWeeklyRoster();

            $report = $this->service->getMonthlyAbsenceReport(
                Carbon::parse('2026-08-03'),
                Carbon::parse('2026-08-09'),
            );

            $row = fn (int $id) => $report['employees']->firstWhere('employee_id', $id);

            // Always-present: 7 present days, no absent day.
            $this->assertNotNull($row($roster['alwaysPresent']->id));
            $this->assertSame(7, $row($roster['alwaysPresent']->id)['present']);
            $this->assertSame([], $row($roster['alwaysPresent']->id)['absent_dates']);

            // Always-absent: 0 present days, every day absent.
            $this->assertSame(0, $row($roster['alwaysAbsent']->id)['present']);
            $this->assertSame(7, count($row($roster['alwaysAbsent']->id)['absent_dates']));

            // Vacation mid-week: 3 vacation days (08-03..05), then absent the
            // remaining 4 days (08-06..09) — the vacation never leaks into
            // absence and the post-vacation days are never excused.
            $vacationDetails = collect($row($roster['vacationMidWeek']->id)['day_details']);
            $this->assertSame(3, $vacationDetails->where('status', 'vacation')->count());
            $this->assertSame(['2026-08-06', '2026-08-07', '2026-08-08', '2026-08-09'], $row($roster['vacationMidWeek']->id)['absent_dates']);

            // Holiday employee: 08-06 is holiday (never absent), the other six
            // days are absent.
            $holidayDetails = collect($row($roster['holidayEmployee']->id)['day_details']);
            $this->assertSame(1, $holidayDetails->where('status', 'holiday')->count());
            $this->assertSame(6, count($row($roster['holidayEmployee']->id)['absent_dates']));
            $this->assertNotContains('2026-08-06', $row($roster['holidayEmployee']->id)['absent_dates']);

            // Open-session employee: present on the open-session day itself
            // (08-03, he did check in) and absent every following day —
            // including 08-04, the day right after the open session
            // (regression guard).
            $this->assertSame(1, $row($roster['openSessionEmployee']->id)['present']);
            $this->assertSame(6, count($row($roster['openSessionEmployee']->id)['absent_dates']));
            $this->assertContains('2026-08-04', $row($roster['openSessionEmployee']->id)['absent_dates']);
            $this->assertNotContains('2026-08-03', $row($roster['openSessionEmployee']->id)['absent_dates']);

            // Cross-check the monthly absent set against the daily absent list
            // day by day: the two views can never disagree.
            foreach ($report['employees'] as $employee) {
                foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07', '2026-08-08', '2026-08-09'] as $dayStr) {
                    $absentList = $this->service->getAbsentEmployees(Carbon::parse($dayStr));
                    $dailyAbsent = $absentList->contains($employee['employee_id']);
                    $monthlyAbsent = in_array($dayStr, $employee['absent_dates'], true);
                    $this->assertSame(
                        $dailyAbsent,
                        $monthlyAbsent,
                        sprintf('Daily and monthly must agree for employee %s on %s.', $employee['employee_id'], $dayStr)
                    );
                }
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Active employees without a rotation assignment are invisible to the
     * absence report (never expected, never absent) — even when they punch
     * and work every day. They must be surfaced so HR can assign rotations.
     */
    public function test_unassigned_active_employee_is_surfaced_and_not_expected(): void
    {
        $date = Carbon::parse('2026-08-06');

        $unassigned = $this->makeEmployee('EMP10300');
        $assigned = $this->makeEmployee('EMP10301');
        $this->withDayShift($this->assignRotation($assigned));

        $ids = $this->service->getUnassignedEmployeeIds($date);
        $this->assertContains($unassigned->id, $ids, 'An active employee without an assignment must be surfaced as unassigned.');
        $this->assertNotContains($assigned->id, $ids, 'An assigned employee must not be listed as unassigned.');
        $this->assertNotContains($unassigned->id, $this->service->getExpectedEmployees($date), 'Unassigned employees are never expected.');

        // Once assigned a rotation, they leave the unassigned list and become
        // part of the expected roster on their work days.
        $this->withDayShift($this->assignRotation($unassigned));
        $ids = $this->service->getUnassignedEmployeeIds($date);
        $this->assertNotContains($unassigned->id, $ids, 'After assignment the employee must no longer be listed as unassigned.');
        $this->assertContains($unassigned->id, $this->service->getExpectedEmployees($date));
    }

    /**
     * Assert the daily report is internally consistent for one date: the sum
     * of all status buckets equals the expected roster, the absent bucket
     * equals the absent list, and the absent list is a subset of the expected
     * employees.
     */
    private function assertDailyReportReconciles(Carbon $date): void
    {
        $expected = $this->service->getExpectedEmployees($date);
        $breakdown = $this->service->getDailyStatusBreakdown($date);
        $bucketSum = array_sum([
            $breakdown['present'], $breakdown['absent'], $breakdown['on_vacation'],
            $breakdown['on_exception'], $breakdown['incomplete'], $breakdown['holiday'],
            $breakdown['awaiting_arrival'],
        ]);
        $this->assertSame(
            $expected->count(),
            $bucketSum,
            sprintf('Every expected employee must be in exactly one status bucket on %s.', $date->toDateString())
        );

        $absent = $this->service->getAbsentEmployees($date);
        $this->assertSame(
            $breakdown['absent'],
            $absent->count(),
            sprintf('The absent bucket must equal the absent list on %s.', $date->toDateString())
        );
        $this->assertSame(
            [],
            $absent->diff($expected)->values()->all(),
            sprintf('The absent list must be a subset of the expected employees on %s.', $date->toDateString())
        );
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

    /**
     * Attach a live time schedule to the assignment's rotation so
     * resolveTimes() yields a deterministic check-in/out and grace deadline.
     */
    private function attachSchedule(RotationAssignment $assignment, string $inTime, string $outTime, int $lateMargin = 30): RotationAssignment
    {
        $schedule = TimeSchedule::create([
            'company_id' => $assignment->rotation->company_id,
            'name' => 'Schedule '.$assignment->rotation->name,
            'in_time' => $inTime,
            'out_time' => $outTime,
            'late_margin' => $lateMargin,
        ]);
        $assignment->rotation->time_schedule_id = $schedule->id;
        $assignment->rotation->save();

        return $assignment;
    }

    /**
     * Standard day shift 08:00-15:00 with a 30-minute grace.
     */
    private function withDayShift(RotationAssignment $assignment): RotationAssignment
    {
        return $this->attachSchedule($assignment, '08:00', '15:00', lateMargin: 30);
    }

    /**
     * Evening shift 15:00-23:00 — used to create an employee still "awaiting
     * arrival" mid-morning.
     */
    private function withEveningShift(RotationAssignment $assignment): RotationAssignment
    {
        return $this->attachSchedule($assignment, '15:00', '23:00', lateMargin: 30);
    }

    /**
     * A 1-work / 3-rest rotation anchored 2026-08-01 (group A), used for an
     * employee whose target date is a rest day.
     */
    private function assignRestRotation(User $user): RotationAssignment
    {
        $rotation = Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rest Rotation '.$user->employee_code,
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

        return RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);
    }

    /**
     * Build a five-employee roster working every day of 2026-08-03..09:
     * always-present, always-absent, vacation mid-week, holiday-covered, and
     * open-session-from-08-03. Shared by the weekly reconciliation and the
     * monthly-vs-daily cross-check tests.
     *
     * @return array{alwaysPresent: User, alwaysAbsent: User, vacationMidWeek: User, holidayEmployee: User, openSessionEmployee: User}
     */
    private function makeWeeklyRoster(): array
    {
        $alwaysPresent = $this->makeEmployee('EMP10201');
        $this->withDayShift($this->assignRotation($alwaysPresent));

        $alwaysAbsent = $this->makeEmployee('EMP10202');
        $this->withDayShift($this->assignRotation($alwaysAbsent));

        $vacationMidWeek = $this->makeEmployee('EMP10203');
        $this->withDayShift($this->assignRotation($vacationMidWeek));
        $vacationType = VacationType::create(['code' => 'ANNUAL', 'name_ar' => 'سنوية', 'is_active' => true]);
        UserVacationRequest::create([
            'user_id' => $vacationMidWeek->id,
            'vacation_type_id' => $vacationType->id,
            'status' => 'approved',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
        ]);

        $branch = Branch::create([
            'company_id' => $alwaysPresent->company_id,
            'branch_code' => 'BR_WK',
            'branch_name' => 'Week Branch',
            'status' => 1,
        ]);

        $holidayEmployee = $this->makeEmployee('EMP10204');
        $this->withDayShift($this->assignRotation($holidayEmployee));
        $holidayEmployee->branch_id = $branch->id;
        $holidayEmployee->save();

        $openSessionEmployee = $this->makeEmployee('EMP10205');
        $this->withDayShift($this->assignRotation($openSessionEmployee));
        AttendanceSession::create([
            'user_id' => $openSessionEmployee->id,
            'attendance_date' => '2026-08-03',
            'check_in_at' => '2026-08-03 08:00:00',
            'check_out_at' => null,
        ]);

        Holiday::create([
            'name_ar' => 'عطلة أسبوع الاختبار',
            'is_recurring' => false,
            'date' => '2026-08-06',
            'is_active' => true,
            'duration_days' => 1,
            'applies_to_all' => false,
            'applies_to_branches' => [$branch->id],
        ]);

        for ($day = 3; $day <= 9; $day++) {
            AttendanceSession::create([
                'user_id' => $alwaysPresent->id,
                'attendance_date' => sprintf('2026-08-%02d', $day),
                'check_in_at' => sprintf('2026-08-%02d 08:05:00', $day),
                'check_out_at' => null,
            ]);
        }

        return compact('alwaysPresent', 'alwaysAbsent', 'vacationMidWeek', 'holidayEmployee', 'openSessionEmployee');
    }
}
