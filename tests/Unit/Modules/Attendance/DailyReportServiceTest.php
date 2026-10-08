<?php

namespace Tests\Unit\Modules\Attendance;

use Carbon\Carbon;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Models\DailyAttendanceSummary;
use Modules\Attendance\Models\RawAttendanceLog;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Attendance\Services\DailyReportService;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;
use Modules\FingerprintDevices\Models\UserFingerprint;
use Modules\Holidays\Models\Holiday;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Models\ShiftException;
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
        // No session on the report day itself: today's status is absence,
        // yesterday's missing check-out stays as a flag + note (and its own
        // DOCX table) instead of hiding the absence.
        $this->assertSame('absent', $row['status']);
        $this->assertStringContainsString('لم يسجل خروج أمس', $row['notes']);
        // The expected exit comes from the time table (17:00), never from the
        // rotation's own window end (11:50).
        $this->assertSame('08:00', $row['expected_check_in']);
        $this->assertSame('17:00', $row['expected_check_out']);
        $this->assertFalse($row['expected_check_out_next_day']);
    }

    /**
     * The missing-checkout and missing-evening tables read the PREVIOUS duty
     * day's actual punches: the real check-in, and the last raw punch when no
     * genuine checkout was recorded (e.g. an early exit the pipeline never
     * counted as a checkout).
     */
    public function test_previous_day_actuals_feed_missing_checkout_and_evening_tables(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40099');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        // Yesterday: checked in at 07:55, never checked out; the raw punches
        // show an early 13:40 exit the pipeline never counted as a checkout.
        $this->makeOpenSession($user, '2026-08-09 07:55:00');
        foreach (['2026-08-09 07:55:00', '2026-08-09 13:40:00'] as $punch) {
            RawAttendanceLog::create([
                'user_id' => $user->id,
                'punch_time' => $punch,
                'punch_type' => 'check_in',
                'source' => 'device',
                'processed' => true,
            ]);
        }

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('07:55', $row['prev_check_in']);
        $this->assertSame('', $row['prev_check_out']);
        $this->assertSame('13:40', $row['prev_last_punch']);
    }

    /**
     * A same-day checkout before the expected end but inside the schedule's
     * early_margin is not a violation, yet the report must tell it apart
     * from a full checkout with an explicit tolerance note.
     */
    public function test_early_checkout_within_tolerance_adds_note(): void
    {
        $user = $this->makeEmployee('EMP40101');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $schedule = $this->makeTimeSchedule($user, '08:00', '15:00');
        $schedule->early_margin = 30;
        $schedule->save();
        $rotation->update([
            'time_schedule_id' => $schedule->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        // 15 minutes early — inside the 30-minute tolerance.
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-10 14:45:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('present', $row['status']);
        $this->assertStringContainsString('خروج مبكر ضمن السماحية', $row['notes']);
    }

    /**
     * An early checkout beyond the schedule's early_margin is a real early
     * leave, never a tolerance case — no tolerance note may appear.
     */
    public function test_early_checkout_beyond_tolerance_adds_no_note(): void
    {
        $user = $this->makeEmployee('EMP40102');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $schedule = $this->makeTimeSchedule($user, '08:00', '15:00');
        $schedule->early_margin = 30;
        $schedule->save();
        $rotation->update([
            'time_schedule_id' => $schedule->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        // 60 minutes early — beyond the 30-minute tolerance.
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-10 14:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertStringNotContainsString('ضمن السماحية', $row['notes']);
    }

    /**
     * An overnight duty whose departure-morning checkout lands before the
     * expected end but inside the tolerance (e.g. 07:45 for an 08:00 duty)
     * carries the tolerance note qualified with yesterday's date.
     */
    public function test_previous_day_early_checkout_within_tolerance_adds_note(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP40103');
        $this->assignOneDayDuty($user, '2026-08-09');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $schedule = $this->makeOvernightSchedule($user);
        $schedule->early_margin = 30;
        $schedule->save();
        $rotation->update(['time_schedule_id' => $schedule->id]);
        // Duty 08-09, departure checkout next morning 15 minutes early.
        $this->makeCompleteSession($user, '2026-08-09 08:00:00', '2026-08-10 07:45:00');
        foreach (['2026-08-09 08:00:00', '2026-08-09 18:00:00'] as $punch) {
            RawAttendanceLog::create([
                'user_id' => $user->id,
                'punch_time' => $punch,
                'punch_type' => 'check_in',
                'source' => 'device',
                'processed' => true,
            ]);
        }

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertStringContainsString('خروج مبكر ضمن السماحية أمس', $row['notes']);
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

        $this->assertTrue($row['has_incomplete_punch']);
        // The rotation has an 08:00 time schedule and it is noon: the arrival
        // deadline has passed, so the employee is absent while yesterday's
        // missing check-out stays flagged.
        $this->assertSame('absent', $row['status']);
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
        // No time schedule means no known check-in: the arrival deadline is
        // the end of the day, so at noon the employee is still awaiting
        // arrival (not absent), while yesterday's missing check-out is kept.
        $this->assertSame('awaiting', $row['status']);
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

        $this->assertTrue($row['has_incomplete_punch']);
        // Same awaiting rule as above: no time schedule, noon report, the
        // arrival deadline (end of day) has not passed yet.
        $this->assertSame('awaiting', $row['status']);
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
        $this->makeCompleteSession($user, '2026-08-09 08:03:00', '2026-08-09 15:35:00');
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
        // The rotation has an 08:00 time schedule and it is noon: the arrival
        // deadline has passed, so the employee is absent while yesterday's
        // missing check-out stays flagged.
        $this->assertSame('absent', $row['status']);
    }

    /**
     * Rotations without a time schedule have no expected check-out time, but
     * the rotation still defines an absolute exit window. Yesterday's open
     * session is still listed: they were expected to work, they came in and
     * they never left.
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
        // 08-11 is a rest day of this 1-day duty: today's status is rest,
        // yesterday's missing check-out stays as a flag + note.
        $this->assertSame('rest', $row['status']);
        $this->assertSame('لم يسجل خروج أمس (10-08)', $row['notes']);
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
        // 08-11 is a rest day of this 1-day duty: today's status is rest,
        // yesterday's missing check-out stays as a flag + note.
        $this->assertSame('rest', $row['status']);
        $this->assertSame('لم يسجل خروج أمس (10-08)', $row['notes']);
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
        $this->assertSame('rest', $row['status']);

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
     * whose previous per-day sessions closed normally must still NOT be
     * flagged: only the last day of the block waits for the departure-morning
     * checkout.
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
        // Per-day sessions, like the live pipeline creates for continuous duty:
        // days 1-2 closed with their evening checkout, day 3 (last of the
        // block) still open waiting for the departure morning.
        $this->makeCompleteSession($user, '2026-08-10 07:00:00', '2026-08-10 19:00:00');
        $this->makeCompleteSession($user, '2026-08-11 07:00:00', '2026-08-11 19:00:00');
        $this->makeOpenSession($user, '2026-08-12 07:00:00');

        $report = $this->service->build('2026-08-12', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch'], '3-day duty is still inside its departure window.');
        $this->assertSame('present', $row['status']);
    }

    /**
     * Mid-block duty days close their own session with the same-evening
     * checkout punch. A missed one belongs to the EVENING table only: the
     * checkout table is reserved for final checkouts (same-day exit of day
     * duties, departure-morning exit of last block days).
     */
    public function test_mid_block_open_session_is_flagged_next_day(): void
    {
        $this->travelTo('2026-08-12 12:00:00');

        $user = $this->makeEmployee('EMP40016');
        $this->assignThreeDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        // Day 2 (mid-block) never recorded its evening checkout: its deadline
        // was the end of the same-day exit window (09:00), long past.
        $this->makeCompleteSession($user, '2026-08-10 07:00:00', '2026-08-10 19:00:00');
        $this->makeOpenSession($user, '2026-08-11 07:00:00');
        $this->makeOpenSession($user, '2026-08-12 07:00:00');

        $report = $this->service->build('2026-08-12', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertTrue($row['has_missing_evening_punch']);
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
        // 08-13 is the departure morning (rest): today's status is rest,
        // yesterday's missing check-out stays as a flag + note.
        $this->assertSame('rest', $row['status']);
        $this->assertSame('لم يسجل خروج أمس (12-08)', $row['notes']);
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

        // Device punch at 03:05 wall time (stored naive-local like production
        // punches); no session created.
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
     * Regression: an employee absent on the report day who missed yesterday's
     * check-out must still appear as غياب (production: فاتنه ظافر حاج خليل on
     * 2026-09-08 was hidden from the غياب table because yesterday's violation
     * overrode today's status). Yesterday's violation stays as a flag + note
     * (and its own DOCX table) so the employee is listed in BOTH tables.
     */
    public function test_absent_today_with_yesterday_missing_checkout_stays_absent(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP50010');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        // Yesterday: checked in, never checked out (deadline passed).
        $this->makeOpenSession($user, '2026-08-09 08:00:00');
        // Today: no session at all.

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('absent', $row['status']);
        $this->assertTrue($row['has_incomplete_punch']);
        // No punch today: the column must be empty, never yesterday's time.
        $this->assertSame('', $row['check_in']);
        $this->assertStringContainsString('عدد أيام الغياب خلال الشهر', $row['notes']);
        $this->assertStringContainsString('لم يسجل خروج أمس', $row['notes']);
    }

    /**
     * Regression: an employee present on the report day who missed yesterday's
     * check-out must show today's punch and today's status (production: nine
     * employees on 2026-09-08 were shown with yesterday's check-in time and
     * hidden from the حاضر/متأخر tables).
     */
    public function test_present_today_with_yesterday_missing_checkout_shows_today_punch(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP50011');
        $this->assignOpenWorkEveryDay($user);
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        // Yesterday: checked in, never checked out (deadline passed).
        $this->makeOpenSession($user, '2026-08-09 07:55:00');
        // Today: checked in on time.
        $this->makeOpenSession($user, '2026-08-10 08:13:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('present', $row['status']);
        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertSame('08:13', $row['check_in']);
        $this->assertStringContainsString('لم يسجل خروج أمس', $row['notes']);
    }

    /**
     * Employees whose fingerprint is not enrolled on the device can never
     * punch, so they must not sit in the غياب table every day as noise: the
     * absent filter and the absent counter skip them while the
     * no-fingerprint filter (and its DOCX table) still lists them.
     */
    public function test_absent_filter_hides_employees_without_enrolled_fingerprint(): void
    {
        $unregistered = $this->makeEmployee('EMP50020');
        $this->assignOpenWorkEveryDay($unregistered);

        $registered = $this->makeEmployee('EMP50021');
        $this->registerFingerprint($registered);
        $this->assignOpenWorkEveryDay($registered);

        // No sessions for either: both are absent today, only the registered
        // one belongs in the غياب table.
        $report = $this->service->build('2026-08-06', '09:00');
        $this->assertSame('absent', $report['rows']->firstWhere('id', $unregistered->id)['status']);
        $this->assertTrue($report['rows']->firstWhere('id', $unregistered->id)['has_no_fingerprint']);

        $absent = $this->service->build('2026-08-06', '09:00', null, null, null, 'absent');
        $this->assertNull($absent['rows']->firstWhere('id', $unregistered->id));
        $this->assertNotNull($absent['rows']->firstWhere('id', $registered->id));

        $unfiltered = $this->service->build('2026-08-06', '09:00');
        $this->assertSame(
            $unfiltered['rows']->where('status', 'absent')->where('has_no_fingerprint', false)->count(),
            $unfiltered['stats']['absent']
        );

        $noFingerprint = $this->service->build('2026-08-06', '09:00', null, null, null, 'no_fingerprint');
        $this->assertNotNull($noFingerprint['rows']->firstWhere('id', $unregistered->id));
    }

    /**
     * The report can be filtered by several departments at once; a single id
     * keeps working for backward compatibility.
     */
    public function test_report_can_filter_by_multiple_departments(): void
    {
        $companyId = Company::create(['company_code' => 'CMP_DEPTS', 'company_name' => 'Depts Co', 'status' => 1])->id;
        $branchId = Branch::create([
            'company_id' => $companyId,
            'branch_code' => 'BR_DEPTS',
            'branch_name' => 'الفرع',
            'status' => 1,
        ])->id;
        $deptA = Department::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_code' => 'DEPTA',
            'department_name' => 'القسم أ',
            'status' => 1,
        ]);
        $deptB = Department::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_code' => 'DEPTB',
            'department_name' => 'القسم ب',
            'status' => 1,
        ]);

        $userA = $this->makeEmployee('EMP60001');
        $userA->update(['department_id' => $deptA->id]);
        $this->assignOpenWorkEveryDay($userA);

        $userB = $this->makeEmployee('EMP60002');
        $userB->update(['department_id' => $deptB->id]);
        $this->assignOpenWorkEveryDay($userB);

        $outsider = $this->makeEmployee('EMP60003');
        $this->assignOpenWorkEveryDay($outsider);

        $report = $this->service->build('2026-08-06', '09:00', null, [$deptA->id, $deptB->id]);
        $this->assertNotNull($report['rows']->firstWhere('id', $userA->id));
        $this->assertNotNull($report['rows']->firstWhere('id', $userB->id));
        $this->assertNull($report['rows']->firstWhere('id', $outsider->id));

        // A single id still filters to that department only.
        $single = $this->service->build('2026-08-06', '09:00', null, $deptA->id);
        $this->assertNotNull($single['rows']->firstWhere('id', $userA->id));
        $this->assertNull($single['rows']->firstWhere('id', $userB->id));
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

    /**
     * An expected employee with no punch whose check-in deadline has not
     * passed yet is awaiting arrival — never absent. Same rule as smart
     * absence: a 10:00 shift is not absent at 09:00 even with a 09:00 cutoff.
     */
    public function test_employee_inside_arrival_window_is_awaiting_not_absent(): void
    {
        $this->travelTo('2026-08-10 09:00:00');

        $user = $this->makeEmployee('EMP60001');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '10:00', '18:00')->id,
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('awaiting', $row['status']);
        $this->assertSame(0, $report['stats']['absent']);
        $this->assertSame(1, $report['stats']['awaiting']);
    }

    /**
     * Once the same deadline passes with no punch, the awaiting employee
     * becomes absent.
     */
    public function test_awaiting_employee_becomes_absent_after_deadline(): void
    {
        $this->travelTo('2026-08-10 11:00:00');

        $user = $this->makeEmployee('EMP60002');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '10:00', '18:00')->id,
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('absent', $row['status']);
        $this->assertSame(0, $report['stats']['awaiting'] ?? 0);
    }

    /**
     * Lateness respects the rotation's own deadline: arriving at 09:30 for a
     * 10:00 shift is on time even though it is past the 09:00 report cutoff.
     */
    public function test_late_respects_rotation_deadline_over_cutoff(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60003');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '10:00', '18:00')->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 09:30:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('present', $row['status']);
    }

    /**
     * Late minutes are reported as a positive count of minutes past the
     * lateness threshold (Carbon 3 signed diffs previously rendered them
     * negative for every late employee).
     */
    public function test_late_minutes_are_positive(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60006');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '17:00')->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 09:25:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('late', $row['status']);
        $this->assertSame(25, $row['late_minutes']);
    }

    /**
     * Employees without any rotation assignment are reported as unassigned —
     * never as rest and never as absent.
     */
    public function test_employee_without_assignment_is_unassigned(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60007');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('unassigned', $row['status']);
        $this->assertSame(1, $report['stats']['unassigned']);
    }

    /**
     * An overnight checkout keeps its next-day date flagged so the UI can
     * mark it (+1) instead of showing a time earlier than the check-in.
     */
    public function test_overnight_checkout_is_flagged_next_day(): void
    {
        $this->travelTo('2026-08-11 12:00:00');

        $user = $this->makeEmployee('EMP60008');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:10:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('08:10', $row['check_out']);
        $this->assertTrue($row['check_out_next_day']);
    }

    /**
     * A day-duty session closed by the NEXT day's checkout punch still hides
     * a forgotten exit: the 15:02 punch belongs to the new duty day, so the
     * previous day is flagged as missing checkout.
     */
    public function test_session_closed_by_next_day_punch_is_flagged(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60009');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 15:02:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
    }

    /**
     * A session auto-closed by the nightly job (fabricated checkout, never a
     * real punch) is still a missing checkout on the next day's report.
     */
    public function test_auto_closed_session_is_flagged(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60010');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
        ]);
        $session = $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-10 15:00:00');
        $session->forceFill(['notes' => 'أغلق تلقائياً: موعد الخروج المتوقع حسب جدول الوقت قد انتهى'])->save();

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
    }

    /**
     * An overnight duty whose checkout legitimately lands on the departure
     * morning is NOT a missing checkout.
     */
    public function test_overnight_next_morning_checkout_is_not_flagged(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60011');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:05:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
    }

    /**
     * An overnight duty with a morning check-in and a departure checkout but
     * no evening punch is flagged for the missing evening punch (the evening
     * presence is its own obligation, independent of the checkout).
     */
    public function test_missing_evening_punch_is_flagged(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60012');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:05:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_missing_evening_punch']);
        $this->assertSame(1, $report['stats']['evening']);
        $this->assertStringContainsString('لم يسجل البصمة المسائية أمس', $row['notes']);
    }

    /**
     * An evening device punch fulfils the obligation, even when no session
     * was built from it.
     */
    public function test_evening_punch_fulfils_obligation(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60013');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:05:00');
        // 17:00 UTC = 20:00 local: an evening presence punch, no session.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-10 17:00:00',
            'punch_type' => 'extra',
            'source' => 'device',
            'processed' => true,
        ]);

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_missing_evening_punch']);
    }

    /**
     * When the time table configures an explicit third (evening) punch
     * window, only a punch inside it fulfils the evening obligation — the
     * legacy "anything after the entry window" rule no longer applies.
     */
    public function test_third_punch_window_governs_evening_obligation(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60016');
        $this->assignOneDayDuty($user, '2026-08-10');
        $schedule = $this->makeOvernightSchedule($user);
        $schedule->third_punch_start = '18:00';
        $schedule->third_punch_end = '23:00';
        $schedule->save();
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $schedule->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:05:00');
        // 13:00 is after the legacy 12:00 threshold but outside the explicit
        // 18:00-23:00 third-punch window: the obligation stays unfulfilled.
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-10 13:00:00',
            'punch_type' => 'extra',
            'source' => 'device',
            'processed' => true,
        ]);

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_missing_evening_punch']);
    }

    /**
     * A punch inside the explicit third-punch window fulfils the evening
     * obligation.
     */
    public function test_punch_inside_third_punch_window_fulfils_obligation(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60017');
        $this->assignOneDayDuty($user, '2026-08-10');
        $schedule = $this->makeOvernightSchedule($user);
        $schedule->third_punch_start = '18:00';
        $schedule->third_punch_end = '23:00';
        $schedule->save();
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $schedule->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 08:05:00');
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-10 20:00:00',
            'punch_type' => 'extra',
            'source' => 'device',
            'processed' => true,
        ]);

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_missing_evening_punch']);
    }

    /**
     * A flagged missing checkout on a day covered by an approved leave names
     * the context (e.g. retroactive sick leave with punches) instead of
     * leaving the reviewer guessing — without hiding the violation.
     */
    public function test_flagged_checkout_names_leave_context(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP60014');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $type = VacationType::create([
            'code' => 'SICK60014',
            'name_ar' => 'إجازة مرضية',
            'name_en' => 'Sick Leave',
            'is_active' => true,
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id,
            'vacation_type_id' => $type->id,
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'days_count' => 1,
            'working_days_count' => 1,
            'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertStringContainsString('لم يسجل خروج أمس', $row['notes']);
        $this->assertStringContainsString('يوجد إجازة أو استثناء بتاريخ الدوام', $row['notes']);
        // Leave excuses the evening obligation: one violation, one message.
        $this->assertFalse($row['has_missing_evening_punch']);
    }

    /**
     * Day duties never carry an evening punch: the checkout already has its
     * own column, so the evening column stays empty for them.
     */
    public function test_day_duty_has_no_evening_punch(): void
    {
        $this->travelTo('2026-08-10 18:00:00');

        $user = $this->makeEmployee('EMP60015');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-10 15:00:00');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertNull($row['evening_punch']);
    }

    /**
     * A mid-block session closed without a real evening checkout is diverted
     * to the evening table — but a RECORDED evening punch fulfils the evening
     * side (even when its session was later auto-closed), so nothing is
     * flagged at all.
     */
    public function test_mid_block_with_recorded_evening_punch_is_not_flagged(): void
    {
        $this->travelTo('2026-08-12 12:00:00');

        $user = $this->makeEmployee('EMP40017');
        $this->assignThreeDayDuty($user, '2026-08-10');
        $rotation = RotationAssignment::where('employee_id', $user->id)->first()->rotation;
        $rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
            'out_ahead_margin' => '07:30:00',
            'out_above_margin' => '09:00:00',
        ]);
        // Evening punch recorded (20:00 local) but its session was auto-closed
        // by the nightly job — the fabricated checkout must not surface as a
        // missing evening punch.
        $session = $this->makeCompleteSession($user, '2026-08-11 07:00:00', '2026-08-11 19:00:00');
        $session->forceFill(['notes' => 'أغلق تلقائياً: موعد الخروج المتوقع حسب جدول الوقت قد انتهى'])->save();
        RawAttendanceLog::create([
            'user_id' => $user->id,
            'punch_time' => '2026-08-11 17:00:00',
            'punch_type' => 'extra',
            'source' => 'device',
            'processed' => true,
        ]);
        $this->makeOpenSession($user, '2026-08-12 07:00:00');

        $report = $this->service->build('2026-08-12', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertFalse($row['has_missing_evening_punch']);
    }

    public function test_checkout_only_session_is_not_presence(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60004');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '17:00')->id,
        ]);
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-10',
            'check_in_at' => null,
            'check_out_at' => '2026-08-10 08:05:00',
            'status' => 'present',
            'session_type' => 'normal',
            'source' => 'device',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('absent', $row['status']);
    }

    /**
     * Nobody is expected (or absent) before being hired — same rule as smart
     * absence.
     */
    public function test_employee_hired_after_report_date_is_excluded(): void
    {
        $user = $this->makeEmployee('EMP60005');
        $this->assignOpenWorkEveryDay($user);
        User::query()->whereKey($user->id)->update(['hire_date' => '2026-09-01']);

        $report = $this->service->build('2026-08-10', '09:00');

        $this->assertNull($report['rows']->firstWhere('id', $user->id));
    }

    /**
     * An approved HR attendance-exemption removes the employee from the
     * report — same rule as smart absence.
     */
    public function test_exempt_employee_is_excluded(): void
    {
        $user = $this->makeEmployee('EMP60006');
        $this->assignOpenWorkEveryDay($user);
        User::query()->whereKey($user->id)->update([
            'attendance_exemption_type' => 'mission',
            'attendance_exemption_from' => '2026-08-01',
            'attendance_exemption_to' => '2026-08-31',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');

        $this->assertNull($report['rows']->firstWhere('id', $user->id));
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
        // Phase 1: RotationEngine indexes FORWARD — position N is anchor+N
        // days (see engine docblock). The old backward-index comment/pattern
        // produced a 1-day duty, which broke both 3-day scenarios.
        return $this->assignOpenRotation($user, [1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], $start);
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

    // =========================================================================
    // Audit suite — every number the report prints must be defensible
    // =========================================================================

    /**
     * AUDIT: the stat cards describe the WHOLE report day, never the subset
     * the table happens to be filtered to. A manager filtering "غياب" must
     * still read the true late/present counts.
     */
    public function test_stats_always_describe_the_whole_report_not_the_filtered_subset(): void
    {
        $present = $this->makeEmployee('EMP90001');
        $this->assignOpenWorkEveryDay($present);
        $this->makeCompleteSession($present, '2026-08-10 08:00:00');

        $late = $this->makeEmployee('EMP90002');
        $this->assignOpenWorkEveryDay($late);
        RotationAssignment::where('employee_id', $late->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($late, '08:00', '17:00')->id,
        ]);
        $this->makeCompleteSession($late, '2026-08-10 09:25:00');

        $absent = $this->makeEmployee('EMP90003');
        $this->registerFingerprint($absent);
        $this->assignOpenWorkEveryDay($absent);

        $filtered = $this->service->build('2026-08-10', '09:00', null, null, null, 'absent');

        // The TABLE is filtered...
        $this->assertCount(1, $filtered['rows']);
        $this->assertSame($absent->id, $filtered['rows']->first()['id']);

        // ...but every CARD still describes the whole day.
        $this->assertSame(3, $filtered['stats']['total']);
        $this->assertSame(1, $filtered['stats']['absent']);
        $this->assertSame(1, $filtered['stats']['late']);
        $this->assertSame(1, $filtered['stats']['present']);
    }

    /**
     * AUDIT: the no-fingerprint filter relabels every row it keeps. Filtering a
     * table must never move a single number on the cards.
     */
    public function test_stats_survive_the_no_fingerprint_filter_rewriting_status(): void
    {
        $unregisteredAbsent = $this->makeEmployee('EMP90010');
        $this->assignOpenWorkEveryDay($unregisteredAbsent);

        $registered = $this->makeEmployee('EMP90011');
        $this->registerFingerprint($registered);
        $this->assignOpenWorkEveryDay($registered);
        $this->makeCompleteSession($registered, '2026-08-10 08:00:00');

        $all = $this->service->build('2026-08-10', '09:00');
        $filtered = $this->service->build('2026-08-10', '09:00', null, null, null, 'no_fingerprint');

        $this->assertCount(1, $filtered['rows']);
        $this->assertSame('no_fingerprint', $filtered['rows']->first()['status']);
        $this->assertSame($all['stats'], $filtered['stats']);
        $this->assertSame(2, $filtered['stats']['total']);
        $this->assertSame(1, $filtered['stats']['no_fingerprint']);
    }

    /**
     * AUDIT: a mission exception says WHERE the employee was sent, not that
     * they failed to attend. Someone who badged in and out is classified by
     * their punches and stays in the حاضر/متأخر tables.
     */
    public function test_mission_exception_does_not_override_recorded_attendance(): void
    {
        $user = $this->makeEmployee('EMP90020');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '17:00')->id,
        ]);
        $this->makeCompleteSession($user, '2026-08-10 09:25:00');

        $this->makeMissionException($user, '2026-08-10');

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('late', $row['status']);
        $this->assertSame(1, $report['stats']['late']);
        $this->assertSame(0, $report['stats']['mission'] ?? 0);
    }

    /**
     * AUDIT: a mission exception must not turn a rotation REST day into a
     * mission — the employee was never scheduled.
     */
    public function test_mission_exception_does_not_turn_a_rest_day_into_a_mission(): void
    {
        $user = $this->makeEmployee('EMP90021');
        // 1 work + 3 rest anchored 08-03 => 08-10 is a rest day.
        $this->assignOpenRotation($user, [1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0], '2026-08-03');

        $this->makeMissionException($user, '2026-08-10');

        $report = $this->service->build('2026-08-10', '09:00');

        $this->assertSame('rest', $report['rows']->firstWhere('id', $user->id)['status']);
    }

    /**
     * AUDIT: an approved leave day is never counted as an absence, even when a
     * stale daily summary still says "absent" for it.
     */
    public function test_monthly_absence_counter_excludes_approved_leave_days(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90040');
        $this->registerFingerprint($user);
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '17:00')->id,
        ]);

        $annual = VacationType::create([
            'code' => 'ANN90040', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        foreach (['2026-08-03', '2026-08-04'] as $day) {
            DailyAttendanceSummary::create([
                'user_id' => $user->id,
                'summary_date' => $day,
                'status' => 'absent',
                'calculated_at' => now(),
            ]);
        }
        UserVacationRequest::create([
            'user_id' => $user->id,
            'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'days_count' => 3,
            'working_days_count' => 3,
            'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('absent', $row['status']);
        // Only 08-10 counts: 08-03 and 08-04 are covered by the approved leave.
        $this->assertCountNote($row['notes'], 'عدد أيام الغياب خلال الشهر', 1);
    }

    /**
     * AUDIT: two approved requests covering the same days must not inflate the
     * vacation-day counter — the same calendar day is one day off, not two.
     */
    public function test_overlapping_approved_requests_count_each_day_once(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90041');
        $this->assignOpenWorkEveryDay($user);

        $annual = VacationType::create([
            'code' => 'ANN90041', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'days_count' => 1, 'working_days_count' => 1, 'status' => 'approved',
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-09', 'end_date' => '2026-08-11',
            'days_count' => 3, 'working_days_count' => 3, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        // Distinct days up to the report day: 08-09 and 08-10. The 08-10 request
        // adds nothing — 08-10 is already covered by the other one — and 08-11
        // has not happened yet, so the two never report the same day twice.
        $this->assertCountNote($row['notes'], 'عدد أيام الإجازة خلال السنة', 2);
    }

    /**
     * The leaves table reports the DAYS TAKEN, never the number of requests.
     *
     * An employee holding a 1-day leave plus a 3-day leave has taken four days.
     * Counting requests answered "2", which a reviewer reads as "two days off"
     * and which silently under-reports everyone with more than one leave.
     */
    public function test_leave_note_sums_the_days_of_every_approved_request(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90042');
        $this->assignOpenWorkEveryDay($user);

        $annual = VacationType::create([
            'code' => 'ANN90042', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        // One 1-day leave in a PREVIOUS month of the same year: it must still be
        // part of the total, because the counter is the year to date, not the
        // month the report happens to be looking at.
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-07-30', 'end_date' => '2026-07-30',
            'days_count' => 1, 'working_days_count' => 1, 'status' => 'approved',
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-08', 'end_date' => '2026-08-10',
            'days_count' => 3, 'working_days_count' => 3, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('leave', $row['status']);
        // 1 + 3 = 4 days taken, NOT 2 requests.
        $this->assertCountNote($row['notes'], 'عدد أيام الإجازة خلال السنة', 4);
    }

    /**
     * The counter is scoped to the report's YEAR, matching how the leave
     * balance is accounted (keyed by year and refreshed annually).
     *
     * A lifetime counter would keep climbing for the length of an employee's
     * service, and — far worse — count days from a FUTURE year into this
     * year's report.
     */
    public function test_leave_days_never_carry_over_into_the_next_year(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90045');
        $this->assignOpenWorkEveryDay($user);

        $annual = VacationType::create([
            'code' => 'ANN90045', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-08', 'end_date' => '2026-08-10',
            'days_count' => 3, 'working_days_count' => 3, 'status' => 'approved',
        ]);
        // Last year's leave, already approved: it belongs to the 2025 report.
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2025-12-28', 'end_date' => '2025-12-30',
            'days_count' => 3, 'working_days_count' => 3, 'status' => 'approved',
        ]);
        // Next year's approved leave must not leak into this year's report.
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2027-01-05', 'end_date' => '2027-01-09',
            'days_count' => 5, 'working_days_count' => 5, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        // Only the three 2026 days: not last year's, not next year's.
        $this->assertCountNote($row['notes'], 'عدد أيام الإجازة خلال السنة', 3);
    }

    /**
     * The report documents what has HAPPENED: a day of an approved leave that
     * has not arrived yet is not counted as a day taken.
     */
    public function test_leave_days_stop_at_the_report_date(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90046');
        $this->assignOpenWorkEveryDay($user);

        $annual = VacationType::create([
            'code' => 'ANN90046', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        // 08-08..08-12 approved, but the report only covers up to 08-10.
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-08', 'end_date' => '2026-08-12',
            'days_count' => 5, 'working_days_count' => 5, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('leave', $row['status']);
        // 08-08, 08-09 and 08-10 — the report day itself still counts.
        $this->assertCountNote($row['notes'], 'عدد أيام الإجازة خلال السنة', 3);
    }

    /**
     * A rotation employee's leave days are counted only on the days their
     * rotation actually scheduled them to work.
     */
    public function test_leave_days_skip_the_rotation_rest_days(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90043');
        // 1-day duty then 11 rest days: 08-10 is a duty day, 08-11..08-20 are not.
        $this->assignOneDayDuty($user, '2026-08-10');

        $annual = VacationType::create([
            'code' => 'ANN90043', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        // Covers the duty day plus four of that employee's rest days.
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-10', 'end_date' => '2026-08-14',
            'days_count' => 5, 'working_days_count' => 1, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('leave', $row['status']);
        // 08-10 is the only scheduled day in that window.
        $this->assertCountNote($row['notes'], 'عدد أيام الإجازة خلال السنة', 1);
    }

    /**
     * A rotation employee never enters the leaves table on one of their own
     * rest days: nobody takes leave on a day they were never scheduled to work.
     */
    public function test_rotation_employee_on_a_rest_day_is_absent_from_the_leaves_table(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP90044');
        // Duty on 08-03, so 08-10 is a rest day.
        $this->assignOneDayDuty($user, '2026-08-03');

        $annual = VacationType::create([
            'code' => 'ANN90044', 'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual', 'is_active' => true,
        ]);
        UserVacationRequest::create([
            'user_id' => $user->id, 'vacation_type_id' => $annual->id,
            'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'days_count' => 1, 'working_days_count' => 1, 'status' => 'approved',
        ]);

        $report = $this->service->build('2026-08-10', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertSame('rest', $row['status']);
        $this->assertStringNotContainsString('عدد أيام الإجازة خلال السنة', $row['notes']);
    }

    /**
     * AUDIT: one violation, one message. An employee flagged for a missing
     * checkout is never also flagged for a missing evening punch.
     */
    public function test_one_violation_never_produces_two_messages(): void
    {
        $this->travelTo('2026-08-11 18:00:00');

        $user = $this->makeEmployee('EMP90052');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertTrue($row['has_incomplete_punch']);
        $this->assertFalse($row['has_missing_evening_punch']);
    }

    /**
     * AUDIT: an empty scope yields empty rows and zeroed cards.
     */
    public function test_report_over_an_empty_scope_is_empty_and_zeroed(): void
    {
        $report = $this->service->build('2026-08-10', '09:00', null, [999999]);

        $this->assertCount(0, $report['rows']);
        $this->assertSame(0, $report['stats']['total']);
        foreach (['absent', 'late', 'present', 'rest', 'holiday', 'incomplete', 'evening', 'unassigned', 'awaiting'] as $key) {
            $this->assertSame(0, $report['stats'][$key], "stat {$key} must be zero, not missing");
        }
    }

    /**
     * AUDIT: the status cards are mutually exclusive and add up to the total.
     */
    public function test_status_cards_are_mutually_exclusive_and_sum_to_the_total(): void
    {
        $present = $this->makeEmployee('EMP90060');
        $this->assignOpenWorkEveryDay($present);
        $this->makeCompleteSession($present, '2026-08-10 08:00:00');

        $late = $this->makeEmployee('EMP90061');
        $this->assignOpenWorkEveryDay($late);
        RotationAssignment::where('employee_id', $late->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($late, '08:00', '17:00')->id,
        ]);
        $this->makeCompleteSession($late, '2026-08-10 09:25:00');

        $rest = $this->makeEmployee('EMP90062');
        $this->assignOpenRotation($rest, [1, 0, 0, 0, 1, 0, 0, 0, 1, 0, 0, 0], '2026-08-03');

        $this->makeEmployee('EMP90063');

        $report = $this->service->build('2026-08-10', '09:00');
        $stats = $report['stats'];

        $exclusive = ['present', 'late', 'absent', 'leave', 'mission', 'rest', 'holiday', 'awaiting', 'unassigned'];
        $sum = 0;
        foreach ($exclusive as $key) {
            $sum += (int) ($stats[$key] ?? 0);
        }

        $this->assertSame($stats['total'], $sum);
        $this->assertSame(1, $stats['present']);
        $this->assertSame(1, $stats['late']);
        $this->assertSame(1, $stats['rest']);
        $this->assertSame(1, $stats['unassigned']);
    }

    /**
     * AUDIT: a duty with NO resolvable exit deadline is undecidable, not a
     * violation. The nightly cleanup job refuses to close such a session; the
     * report must agree.
     */
    public function test_no_resolvable_exit_deadline_is_never_reported_as_a_violation(): void
    {
        $this->travelTo('2026-08-11 12:00:00');

        $user = $this->makeEmployee('EMP90070');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'out_ahead_margin' => null,
            'out_above_margin' => null,
        ]);
        $this->makeOpenSession($user, '2026-08-10 08:00:00');

        $report = $this->service->build('2026-08-11', '09:00');

        $this->assertFalse($report['rows']->firstWhere('id', $user->id)['has_incomplete_punch']);
    }

    /**
     * AUDIT: an exit recorded AFTER the deadline still counts as recorded. The
     * report judges whether a punch exists, never whether it was punctual.
     */
    public function test_a_late_exit_is_recorded_not_treated_as_a_missing_punch(): void
    {
        $this->travelTo('2026-08-11 20:00:00');

        $user = $this->makeEmployee('EMP90071');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-10 17:05:00');

        $report = $this->service->build('2026-08-11', '09:00');
        $row = $report['rows']->firstWhere('id', $user->id);

        $this->assertFalse($row['has_incomplete_punch']);
        $this->assertSame('17:05', $row['prev_check_out']);
    }

    /**
     * AUDIT: a corrupt row whose checkout precedes its check-in is not an exit.
     */
    public function test_exit_before_the_check_in_is_never_reported_as_an_exit(): void
    {
        $this->travelTo('2026-08-11 20:00:00');

        $user = $this->makeEmployee('EMP90072');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
            'out_ahead_margin' => '14:30:00',
            'out_above_margin' => '18:00:00',
        ]);
        AttendanceSession::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-08-10',
            'check_in_at' => '2026-08-10 14:00:00',
            'check_out_at' => '2026-08-10 09:00:00',
            'status' => 'present',
            'session_type' => 'normal',
            'source' => 'device',
        ]);

        $report = $this->service->build('2026-08-11', '09:00');

        $this->assertSame('', $report['rows']->firstWhere('id', $user->id)['prev_check_out']);
    }

    /**
     * AUDIT: a real exit punch CLEARS the legacy auto-close marker. Left in
     * place, the next morning's report still called the employee "لم يسجل خروج"
     * even after the repair command healed the session.
     */
    public function test_binding_a_real_exit_clears_the_legacy_auto_close_marker(): void
    {
        $this->travelTo('2026-08-11 12:00:00');

        $user = $this->makeEmployee('EMP90080');
        $this->assignOneDayDuty($user, '2026-08-10');
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeOvernightSchedule($user)->id,
        ]);
        $session = $this->makeCompleteSession($user, '2026-08-10 08:00:00', '2026-08-11 10:00:00');
        $session->forceFill(['notes' => 'أغلق تلقائياً: موعد الخروج المتوقع حسب جدول الوقت قد انتهى'])->save();

        $before = $this->service->build('2026-08-11', '09:00')['rows']->firstWhere('id', $user->id);
        $this->assertTrue($before['has_incomplete_punch']);

        app(AttendanceSessionService::class)->closeSession($session->fresh(), Carbon::parse('2026-08-11 07:55:00'));

        $this->assertStringNotContainsString('أغلق تلقائياً', (string) $session->fresh()->notes);

        $after = $this->service->build('2026-08-11', '09:00')['rows']->firstWhere('id', $user->id);
        $this->assertFalse($after['has_incomplete_punch']);
        $this->assertSame('07:55', $after['prev_check_out']);
    }

    /**
     * AUDIT: an unrelated operator note must survive the repair — the marker is
     * stripped as a sentence, never by keyword.
     */
    public function test_repair_keeps_an_unrelated_operator_note(): void
    {
        $this->travelTo('2026-08-11 12:00:00');

        $user = $this->makeEmployee('EMP90081');
        $this->assignOpenWorkEveryDay($user);
        RotationAssignment::where('employee_id', $user->id)->first()->rotation->update([
            'time_schedule_id' => $this->makeTimeSchedule($user, '08:00', '15:00')->id,
        ]);
        $session = $this->makeOpenSession($user, '2026-08-10 08:00:00');
        // Explicit codepoints only: Arabic comma must be U+060C or the strip
        // cannot find the sentence boundary.
        $session->forceFill(['notes' => "أغلق تلقائياً: موعد الخروج المتوقع قد انتهى\u{060C} تم إبلاغ المشرف"])->save();
        // Sanity: the literal is intact.
        $this->assertStringStartsWith('أغلق تلقائياً', $session->fresh()->notes);

        app(AttendanceSessionService::class)->closeSession($session->fresh(), Carbon::parse('2026-08-10 14:40:00'));

        $this->assertSame('تم إبلاغ المشرف', $session->fresh()->notes);

        // Strict equality: no dangling separator, no "ended without a punch"
        // remnant, and no fragment of the old marker survives.
        $this->assertDoesNotMatchRegularExpression('/أغلق|انتهت|بصمة خروج/', $session->fresh()->notes);
    }

    /**
     * Assert a "label: <Arabic-Indic count>" note carries exactly the expected
     * count. The report prefixes every numeral with U+200F to keep RTL order,
     * so a literal comparison against a bare digit never matches.
     */
    private function assertCountNote(string $notes, string $label, int $expected): void
    {
        $gap = '[\s'."\u{200F}\u{200B}\u{00A0}".']*';
        $pattern = '/'.preg_quote($label, '/').':'.$gap.preg_quote($this->toArabicDigits((string) $expected), '/').'/u';

        $this->assertMatchesRegularExpression($pattern, $notes, "Expected \"{$label}\" to read {$expected} in: {$notes}");
    }

    private function toArabicDigits(string $number): string
    {
        return strtr($number, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
        ]);
    }

    /** An approved mission exception covering one calendar day. */
    private function makeMissionException(User $user, string $date): ShiftException
    {
        return ShiftException::create([
            'company_id' => $user->company_id,
            'employee_id' => $user->id,
            'exception_type' => 'mission',
            'source' => 'manual',
            'from_date' => $date,
            'to_date' => $date,
            'status' => 'active',
        ]);
    }
}
