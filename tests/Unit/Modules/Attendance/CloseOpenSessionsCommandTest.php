<?php

namespace Tests\Unit\Modules\Attendance;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Modules\Attendance\Events\SessionUpdated;
use Modules\Attendance\Models\AttendanceSession;
use Modules\Attendance\Services\AttendanceSessionService;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Models\TimeSchedule;
use Modules\Users\Models\User;
use Tests\TestCase;

class CloseOpenSessionsCommandTest extends TestCase
{
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

    private function assignRotation(User $user, string $anchor): RotationAssignment
    {
        $rotation = Rotation::create([
            'company_id' => $user->company_id,
            'name' => 'Rotation '.$user->employee_code,
            'anchor_start_date' => $anchor,
            'pattern' => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
            'cycle_length' => 12,
            'work_days_count' => 12,
            'rest_days_count' => 0,
            'number_of_groups' => 1,
            'grace_minutes' => 0,
            'work_on_holidays' => false,
        ]);
        $group = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
            'start_date' => $anchor,
        ]);

        return RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);
    }

    private function openSession(User $user, string $checkIn, string $date): AttendanceSession
    {
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

    public function test_command_closes_stale_sessions_at_scheduled_exit_time(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60001');
        $assignment = $this->assignRotation($user, '2026-08-01');
        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Schedule',
            'in_time' => '08:00',
            'out_time' => '15:00',
            'out_above_margin' => 30,
        ]);
        $assignment->rotation()->update(['time_schedule_id' => $schedule->id]);

        $this->openSession($user, '2026-08-09 08:00:00', '2026-08-09');

        $exit = Artisan::call('attendance:close-open-sessions', ['--user' => $user->id, '--older-than' => 0]);
        $output = trim(Artisan::output());

        $session = AttendanceSession::where('user_id', $user->id)->first();

        $this->assertSame(0, $exit, "Command failed:\n{$output}");
        $this->assertNotNull($session->check_out_at, "Session was not closed:\n{$output}");
        $this->assertSame('2026-08-09 15:30:00', $session->check_out_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('أغلق تلقائياً', (string) $session->notes);
    }

    public function test_command_dry_run_does_not_change_anything(): void
    {
        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60002');
        $assignment = $this->assignRotation($user, '2026-08-01');
        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Schedule',
            'in_time' => '08:00',
            'out_time' => '15:00',
            'out_above_margin' => 30,
        ]);
        $assignment->rotation()->update(['time_schedule_id' => $schedule->id]);

        $this->openSession($user, '2026-08-09 08:00:00', '2026-08-09');

        Artisan::call('attendance:close-open-sessions', ['--user' => $user->id, '--dry-run' => true]);

        $session = AttendanceSession::where('user_id', $user->id)->first();

        $this->assertNull($session->check_out_at, 'Dry run must not close sessions.');
    }

    public function test_checkout_closes_all_open_sessions_of_the_duty_cycle(): void
    {
        Event::fake([SessionUpdated::class]);

        $this->travelTo('2026-08-10 12:00:00');

        $user = $this->makeEmployee('EMP60003');
        $this->assignRotation($user, '2026-08-01');

        $this->openSession($user, '2026-08-09 00:10:00', '2026-08-09');
        $this->openSession($user, '2026-08-09 07:54:00', '2026-08-09');

        app(AttendanceSessionService::class)->checkOut($user->id, new \DateTimeImmutable('2026-08-09 15:20:58'));

        $sessions = AttendanceSession::where('user_id', $user->id)->get();

        $this->assertSame(2, $sessions->count());
        foreach ($sessions as $session) {
            $this->assertNotNull($session->check_out_at, 'Every open session of the duty day must be closed by one exit punch.');
        }
    }
}
