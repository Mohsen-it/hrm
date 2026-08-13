<?php

namespace Tests\Unit\Modules\Shifts\Services;

use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Models\TimeSchedule;
use Modules\Shifts\Models\TimeScheduleBreak;
use Modules\Shifts\Services\RotationEngine;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Punch windows must be sourced from the Time Schedule (att_time_schedules),
 * the single source of truth for timings, not from the legacy margin fields
 * stored on att_rotations.
 */
class RotationEngineResolveTimesTest extends TestCase
{
    private RotationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(RotationEngine::class);
    }

    public function test_windows_are_derived_from_time_schedule_minutes(): void
    {
        [$user, $assignment] = $this->makeAssignment();

        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Morning',
            'in_time' => '08:00',
            'out_time' => '16:00',
            'in_ahead_margin' => 30,   // window starts 07:30
            'in_above_margin' => 15,   // window ends 08:15
            'out_ahead_margin' => 15,  // window starts 15:45
            'out_above_margin' => 30,  // window ends 16:30
        ]);

        $assignment->rotation()->update(['time_schedule_id' => $schedule->id]);

        $times = $this->engine->resolveTimes($assignment->fresh(['rotation.timeSchedule']));

        $this->assertSame('08:00', $times['check_in']);
        $this->assertSame('16:00', $times['check_out']);
        $this->assertSame('07:30', $times['in_ahead_margin']);
        $this->assertSame('08:15', $times['in_above_margin']);
        $this->assertSame('15:45', $times['out_ahead_margin']);
        $this->assertSame('16:30', $times['out_above_margin']);
    }

    public function test_zero_schedule_margins_fall_back_to_legacy_rotation_windows(): void
    {
        [$user, $assignment] = $this->makeAssignment();

        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Morning',
            'in_time' => '08:00',
            'out_time' => '16:00',
            // margins default to 0 → the schedule carries no window
        ]);

        $assignment->rotation()->update([
            'time_schedule_id' => $schedule->id,
            'in_ahead_margin' => '07:00:00',
            'in_above_margin' => '09:00:00',
            'out_ahead_margin' => '15:00:00',
            'out_above_margin' => '17:00:00',
        ]);

        $times = $this->engine->resolveTimes($assignment->fresh(['rotation.timeSchedule']));

        // Legacy absolute window times are preserved as-is for compatibility.
        $this->assertSame('07:00', $times['in_ahead_margin']);
        $this->assertSame('09:00', $times['in_above_margin']);
        $this->assertSame('15:00', $times['out_ahead_margin']);
        $this->assertSame('17:00', $times['out_above_margin']);
    }

    public function test_snapshot_time_schedule_windows_take_priority_over_live_data(): void
    {
        [$user, $assignment] = $this->makeAssignment();

        $snapshotSchedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Snap',
            'in_time' => '09:00',
            'out_time' => '18:00',
            'in_ahead_margin' => 60,
            'in_above_margin' => 0,
            'out_ahead_margin' => 0,
            'out_above_margin' => 60,
        ]);

        $liveSchedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'Live',
            'in_time' => '10:00',
            'out_time' => '19:00',
            'in_ahead_margin' => 10,
            'in_above_margin' => 10,
            'out_ahead_margin' => 10,
            'out_above_margin' => 10,
        ]);

        $assignment->snapshot_data = [
            'rotation' => ['id' => $assignment->rotation_id],
            'group' => ['id' => $assignment->rotation_group_id],
            'time_schedule' => [
                'id' => $snapshotSchedule->id,
                'in_time' => $snapshotSchedule->in_time,
                'out_time' => $snapshotSchedule->out_time,
                'is_multi_day' => false,
                'late_margin' => 0,
                'early_margin' => 0,
                'in_ahead_margin' => $snapshotSchedule->in_ahead_margin,
                'in_above_margin' => $snapshotSchedule->in_above_margin,
                'out_ahead_margin' => $snapshotSchedule->out_ahead_margin,
                'out_above_margin' => $snapshotSchedule->out_above_margin,
                'breaks' => [],
            ],
        ];
        $assignment->save();

        $assignment->rotation()->update(['time_schedule_id' => $liveSchedule->id]);

        $times = $this->engine->resolveTimes($assignment->fresh(['rotation.timeSchedule']));

        // The snapshot is authoritative: historical schedules stay stable.
        $this->assertSame('09:00', $times['check_in']);
        $this->assertSame('18:00', $times['check_out']);
        $this->assertSame('08:00', $times['in_ahead_margin']);
        $this->assertSame('19:00', $times['out_above_margin']);
    }

    public function test_breaks_are_summed_from_time_schedule(): void
    {
        [$user, $assignment] = $this->makeAssignment();

        $schedule = TimeSchedule::create([
            'company_id' => $user->company_id,
            'name' => 'With Breaks',
            'in_time' => '08:00',
            'out_time' => '17:00',
        ]);

        TimeScheduleBreak::create([
            'schedule_id' => $schedule->id,
            'break_start' => '12:00',
            'duration' => 30,
            'break_end' => '12:30',
        ]);
        TimeScheduleBreak::create([
            'schedule_id' => $schedule->id,
            'break_start' => '15:00',
            'duration' => 15,
            'break_end' => '15:15',
        ]);

        $assignment->rotation()->update(['time_schedule_id' => $schedule->id]);

        $times = $this->engine->resolveTimes($assignment->fresh(['rotation.timeSchedule']));

        $this->assertSame(45, $times['break_minutes']);
    }

    /**
     * @return array{0: User, 1: RotationAssignment}
     */
    private function makeAssignment(): array
    {
        $company = Company::create([
            'company_code' => 'CMP_RESOLVE',
            'company_name' => 'Resolve Company',
            'status' => 1,
        ]);

        $user = User::create([
            'name' => 'Resolve Employee',
            'full_name_ar' => 'موظف',
            'employee_code' => 'RESOLVE1',
            'email' => 'resolve@test.local',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'status' => 1,
            'is_active_employee' => true,
        ]);

        $rotation = Rotation::create([
            'company_id' => $company->id,
            'name' => 'Resolve Rotation',
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

        $assignment = RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => '2026-08-01',
            'end_date' => null,
        ]);

        return [$user, $assignment];
    }
}
