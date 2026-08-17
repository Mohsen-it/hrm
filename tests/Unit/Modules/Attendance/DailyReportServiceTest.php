<?php

namespace Tests\Unit\Modules\Attendance;

use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\DailyReportService;
use Modules\Companies\Models\Company;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Holidays\Models\Holiday;
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
     * The exit deadline comes from the rotation's TIME TABLE (جدول الوقت):
     * the scheduled out_time plus the schedule's grace minutes. The rotation's
     * own absolute out_above_margin (نهاية نافذة الخروج) only describes the
     * physical punch window and must NOT delay the report — a legacy "23:59"
     * window on a rotation whose schedule ends at 17:00 must not postpone the
     * flag to midnight.
     */
    public function test_time_schedule_deadline_wins_over_rotation_absolute_window(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40001');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user)->id, // 08:00 -> 17:00
            'out_ahead_margin' => '11:00:00',
            'out_above_margin' => '11:50:00', // ignored: the time table says 17:00
        ]);
        $this->makeOpenSession($user, '2026-08-09 08:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
        // The expected exit comes from the time table (17:00), never from the
        // rotation's own window end (11:50).
        $this->assertSame('08:00', $row['expected_check_in']);
        $this->assertSame('17:00', $row['expected_check_out']);
        $this->assertFalse($row['expected_check_out_next_day']);
    }

    /**
     * The missing-checkout table is a strict "اليوم السابق" snapshot: an open
     * session on the report day itself is never evaluated, even when its exit
     * window has already ended — it will appear on tomorrow's report instead.
     */
    public function test_same_day_session_is_not_flagged_because_table_is_yesterday_only(): void
    {
        $this->travelTo('2026-08-10 20:00:00');

        $user = $this->makeEmployee('EMP40002');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user)->id, // window ended at 17:00
            'out_ahead_margin' => '13:00:00',
            'out_above_margin' => '14:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

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
        $this->makeOpenSession($user, '2026-08-09 08:30:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('incomplete', $row['status']);
        $this->assertSame('10:00', $row['expected_check_out']);
    }

    /**
     * Rotations without a time schedule have no expected check-out time, but
     * the rotation still defines an absolute exit window. Yesterday's open
     * session is still listed: they were expected to work, they came in and
     * they never left.
     */
    public function test_incomplete_punch_flags_employees_without_time_schedule_on_previous_day(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40004');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => '20:00:00',
            'out_above_margin' => '10:30:00', // overnight exit window
        ]);
        $this->makeOpenSession($user, '2026-08-09 20:15:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
    }

    /**
     * The same rotation window applies for a rotation without a time schedule:
     * once yesterday's absolute window has ended the employee is flagged.
     */
    public function test_incomplete_punch_uses_rotation_window_without_time_schedule(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40005');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:30:00',
        ]);
        $this->makeOpenSession($user, '2026-08-09 07:45:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('incomplete', $row['status']);
    }

    /**
     * An exit deadline that falls BEFORE the session's own check-in cannot
     * belong to that shift. An overnight rotation without a time schedule
     * whose next-morning exit window (07:30-09:30) is read as same-day would
     * "end" at 09:30 on the duty day — before the employee even arrived in
     * the evening. Such an employee must never be flagged as a missed exit.
     */
    public function test_incomplete_punch_not_flagged_when_exit_window_ends_before_check_in(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40017');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:30:00', // morning window, read on the duty day itself
        ]);
        // Evening shift: the employee arrived (17:11) long after the 09:30
        // "deadline" — the window cannot be about this shift.
        $this->makeOpenSession($user, '2026-08-09 17:11:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertNotSame('incomplete', $row['status']);
    }

    /**
     * A real check-out punch anywhere on the duty day proves the employee
     * left — even when an earlier session is still open (e.g. a stray
     * mid-night punch before the real shift). The report must never turn a
     * registered exit into a missing-checkout violation.
     */
    public function test_incomplete_punch_ignores_open_session_when_any_checkout_was_recorded(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40016');
        $this->registerFingerprint($user);
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:30:00',
        ]);
        // A stray open session before the real shift (mid-night punch).
        $this->makeOpenSession($user, '2026-08-09 00:10:00');
        // The real shift: checked in and out at 15:20.
        $this->makeCompleteSession($user, '2026-08-09 10:42:00', '2026-08-09 15:20:58');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch'], 'A registered check-out must clear the violation.');
        $this->assertNotSame('incomplete', $row['status']);
    }

    /**
     * An employee who already recorded a check-out on their main shift
     * session must not be flagged as a missing check-out just because they
     * punched in again later (e.g. stayed after an administrative shift) and
     * left that second session open.
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
        $this->makeCompleteSession($user, '2026-08-09 08:03:00', '15:35:00');
        // Stayed after the shift: a second visit after the exit window opens
        // a new session that stays open. This must not re-flag the employee.
        $this->makeOpenSession($user, '2026-08-09 19:09:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
    }

    /**
     * A session that recorded no check-out still counts as a missing
     * check-out when the employee has NO registered exit punch at all that
     * day: the scheduled exit window applies to the main shift, and an open
     * later session must not clear it.
     */
    public function test_incomplete_punch_still_flags_when_no_checkout_was_recorded_at_all(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40007');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);

        // Main shift never closed, and a later visit is open too: no exit
        // punch was recorded anywhere that day.
        $this->makeOpenSession($user, '2026-08-09 08:03:00');
        $this->makeOpenSession($user, '2026-08-09 19:09:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
    }

    /**
     * An overnight rotation (multi-day schedule) closes its exit window on the
     * next morning. When the daily report is prepared before that window ends
     * (the manager submits it before the end of the shift), an employee who
     * checked in yesterday but has not checked out yet must NOT be flagged yet.
     */
    public function test_overnight_rotation_not_flagged_before_next_day_exit_window(): void
    {
        $this->travelTo('2026-08-11 08:30:00');

        // The overnight schedule (08:00 out, multi-day) carries a 120-minute
        // grace: the time-table deadline is 10:00 on the departure morning.

        $user = $this->makeEmployee('EMP40008');
        $this->assignOneDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch'], 'An overnight shift is still inside its next-day exit window.');
    }

    /**
     * Once the overnight exit window (next morning) has ended, the employee is
     * flagged as a missing check-out from the previous day with a plain note:
     * the time-schedule details live in the expected-exit column, not in the
     * notes any more.
     */
    public function test_overnight_rotation_flagged_after_next_day_exit_window(): void
    {
        $this->travelTo('2026-08-11 10:30:00');

        $user = $this->makeEmployee('EMP40009');
        $this->registerFingerprint($user);
        $this->assignOneDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
        $this->assertSame('لم يسجل خروج أمس', $row['notes']);
    }

    /**
     * The user's 1-3 duty scenario, straight from the time table: check in on
     * the duty day, exit due on the second day at 08:00 + 120 minutes = 10:00.
     * The rotation's absolute exit window (23:59, a legacy value that does not
     * even cover the morning) must be ignored in favour of the time table.
     */
    public function test_one_three_duty_flagged_by_time_table_on_departure_morning(): void
    {
        $this->travelTo('2026-08-11 10:30:00');

        $user = $this->makeEmployee('EMP40013');
        $this->registerFingerprint($user);
        $this->assignOneDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id, // 08:00 out, multi-day, +120 min
            'out_ahead_margin' => '18:30:00',
            'out_above_margin' => '23:59:00', // ignored in favour of the time table
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
        $this->assertSame('لم يسجل خروج أمس', $row['notes']);
        // The expected entry/exit columns come from the rotation's time table:
        // in 08:00, out 08:00 on the next day (اليوم التالي).
        $this->assertSame('08:00', $row['expected_check_in']);
        $this->assertSame('08:00', $row['expected_check_out']);
        $this->assertTrue($row['expected_check_out_next_day']);
    }

    /**
     * While the 1-3 employee is still on their 24h duty (the report day is the
     * duty day itself) they must not be flagged: the exit is due tomorrow.
     */
    public function test_one_three_duty_not_flagged_on_the_duty_day(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40014');
        $this->assignOneDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '18:30:00',
            'out_above_margin' => '23:59:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertSame('present', $row['status']);
    }

    /**
     * The table is strictly a "اليوم السابق" snapshot: the same open session
     * shows up on the report for the day after its duty, and disappears from
     * every later report — it never piles up across days (this was the stale
     * backlog the user complained about).
     */
    public function test_open_session_is_flagged_only_on_the_following_days_report(): void
    {
        $this->travelTo('2026-08-13 12:00:00');

        $user = $this->makeEmployee('EMP40015');
        $this->assignOneDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        // Report for 08-11 (the day after the duty): the session is yesterday's
        // and the 10:00 deadline has passed -> flagged.
        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);
        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);

        // On 08-12 and 08-13 the session is older than yesterday -> gone.
        foreach (['2026-08-12', '2026-08-13'] as $reportDate) {
            $report = $this->service->build($reportDate, '09:00');
            $row = $report['rows']->firstWhere('id', $user->id);
            $this->assertFalse($row['has_incomplete_punch']);
        }
    }

    /**
     * A 3-day duty rotation keeps the employee on site until the morning of the
     * fourth day. While the report is prepared mid-duty (day 3), an employee
     * with open per-day sessions must still NOT be flagged.
     */
    public function test_three_day_duty_not_flagged_while_still_on_duty(): void
    {
        $this->travelTo('2026-08-12 12:00:00');

        $user = $this->makeEmployee('EMP40010');
        $this->assignThreeDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        // Per-day sessions, like the live pipeline creates for continuous duty.
        $this->makeOpenSession($user, '2026-08-10 07:00:00');
        $this->makeOpenSession($user, '2026-08-11 07:00:00');
        $this->makeOpenSession($user, '2026-08-12 07:00:00');

        $report = $this->service->build('2026-08-12', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch'], '3-day duty is still inside its departure window.');
        $this->assertSame('present', $row['status']);
    }

    /**
     * A single open session from the first duty day (legacy single-session
     * model) is older than yesterday on the departure morning, so the strict
     * "اليوم السابق" rule does not list it. Production sessions are per-day
     * (see the next test), which is what the table covers.
     */
    public function test_three_day_duty_single_old_session_is_not_flagged_on_departure_day(): void
    {
        $this->travelTo('2026-08-13 10:30:00');

        $user = $this->makeEmployee('EMP40011');
        $this->assignThreeDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        // Single open session from the first duty day (three days before).
        $this->makeOpenSession($user, '2026-08-10 07:00:00');

        $report = $this->service->build('2026-08-13', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
    }

    /**
     * With the per-day session model (what the pipeline produces for continuous
     * duty) the 3-day duty employee is flagged on the report for the day after
     * the last duty day, because their X-1 session is still open: the note
     * reads "أمس" and the expected exit is the departure morning per the time
     * table.
     */
    public function test_three_day_duty_flagged_via_previous_day_session(): void
    {
        $this->travelTo('2026-08-13 10:30:00');

        $user = $this->makeEmployee('EMP40012');
        $this->registerFingerprint($user);
        $this->assignThreeDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        $this->makeOpenSession($user, '2026-08-10 07:00:00');
        $this->makeOpenSession($user, '2026-08-11 07:00:00');
        $this->makeOpenSession($user, '2026-08-12 07:00:00');

        $report = $this->service->build('2026-08-13', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('incomplete', $row['status']);
        $this->assertSame('لم يسجل خروج أمس', $row['notes']);
        $this->assertSame('08:00', $row['expected_check_in']);
        $this->assertSame('08:00', $row['expected_check_out']);
        $this->assertTrue($row['expected_check_out_next_day']);
    }

    /**
     * The smart-absence report treats a raw device punch as proof of presence
     * even when the session pipeline could not create a session for that date
     * (e.g. an early-morning punch outside the configured check-in window).
     * The daily report must use the same rule so both reports never disagree
     * on who is absent.
     */
    public function test_employee_with_raw_punch_but_no_session_is_not_absent(): void
    {
        $user = $this->makeEmployee('EMP50001');
        $this->assignOpenWorkEveryDay($user);

        // Device punch at 06:05 Asia/Riyadh = 03:05 UTC; no session created.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-06 03:05:04',
            'punch_type' => 'check_in',
            'source' => 'device',
            'processed' => true,
        ]);

        $report = $this->service->build('2026-08-06', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertNotSame('absent', $row['status'], 'A raw device punch must prove presence.');
        $this->assertSame('present', $row['status']);
        $this->assertStringContainsString('بصمة مسجلة دون جلسة', $row['notes']);
    }

    /**
     * An official holiday must never turn expected employees into absentees:
     * smart absence cancels absence for the whole day, so the daily report
     * must show them as "إجازة رسمية" instead of "غياب".
     */
    public function test_official_holiday_does_not_mark_expected_employees_absent(): void
    {
        Holiday::create([
            'name_ar' => 'عيد وطني',
            'name_en' => 'National Day',
            'date' => '2026-08-06',
            'is_recurring' => false,
            'is_active' => true,
            'applies_to_all' => true,
            'duration_days' => 1,
        ]);

        $user = $this->makeEmployee('EMP50002');
        $this->assignOpenWorkEveryDay($user);

        $report = $this->service->build('2026-08-06', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertNotSame('absent', $row['status'], 'An official holiday must not be reported as absence.');
        $this->assertSame('holiday', $row['status']);
    }

    /**
     * A rotation configured to work on holidays is not excused by an official
     * holiday: the employee stays expected and absent without a punch.
     */
    public function test_holiday_does_not_excuse_rotations_that_work_on_holidays(): void
    {
        Holiday::create([
            'name_ar' => 'عيد وطني',
            'name_en' => 'National Day',
            'date' => '2026-08-06',
            'is_recurring' => false,
            'is_active' => true,
            'applies_to_all' => true,
            'duration_days' => 1,
        ]);

        $user = $this->makeEmployee('EMP50003');
        $assignment = $this->assignOpenWorkEveryDay($user);
        $assignment->rotation()->update(['work_on_holidays' => true]);

        $report = $this->service->build('2026-08-06', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('absent', $row['status'], 'Rotations that work on holidays stay accountable on holidays.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function registerFingerprint(User $user): void
    {
        UserFingerprint::create([
            'user_id' => $user->id,
            'finger_id' => 1,
            'template_data' => 'dGVzdA==',
            'template_format' => 'zk-face',
            'is_master' => true,
        ]);
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

    /** 1-day duty rotation: works the start day, rests the following morning. */
    private function assignOneDayDuty(User $user, string $start): RotationAssignment
    {
        return $this->assignOpenRotation($user, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], $start);
    }

    /** 3-day duty rotation: works the start day and the two following days, rests on the fourth morning. */
    private function assignThreeDayDuty(User $user, string $start): RotationAssignment
    {
        // The engine indexes the pattern backward from the anchor: position 0 is
        // the anchor day, positions 11 and 10 are the +1 and +2 days.
        return $this->assignOpenRotation($user, [1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1], $start);
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

    private function makeOvernightSchedule(User $user): TimeSchedule
    {
        return TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Overnight '.$user->employee_code,
            'in_time' => '08:00',
            'out_time' => '08:00',
            'is_multi_day' => true,
            // 120-minute grace: the time-table deadline is 08:00 + 120 = 10:00
            // on the departure morning.
            'out_above_margin' => 120,
        ]);
    }
}
