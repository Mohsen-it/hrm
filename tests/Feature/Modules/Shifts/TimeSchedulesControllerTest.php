<?php

namespace Tests\Feature\Modules\Shifts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Models\Company;
use Modules\Shifts\Http\Controllers\TimeSchedulesController;
use Modules\Shifts\Models\TimeSchedule;
use Tests\TestCase;

/**
 * Feature coverage for {@see TimeSchedulesController}.
 */
class TimeSchedulesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_successful_response_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('time-schedules.index'))->assertOk();
    }

    public function test_create_renders_form_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('time-schedules.create'))->assertOk();
    }

    public function test_store_persists_schedule_and_redirects(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create(['status' => 1]);

        $payload = [
            'name' => 'Test Schedule',
            'in_time' => '08:00',
            'out_time' => '16:00',
            'is_multi_day' => false,
            'late_margin' => 15,
            'early_margin' => 10,
            'breaks' => [],
        ];

        $response = $this->post(route('time-schedules.store'), $payload);

        $response->assertRedirect(route('time-schedules.index'));
        $this->assertDatabaseHas('att_time_schedules', ['name' => 'Test Schedule']);
    }

    public function test_store_rejects_missing_name(): void
    {
        $this->actAsSuperAdmin();

        $this->post(route('time-schedules.store'), [
            'name' => '',
            'in_time' => '08:00',
            'out_time' => '16:00',
        ])->assertSessionHasErrors('name');
    }

    public function test_show_renders_supplied_schedule(): void
    {
        $this->actAsSuperAdmin();
        $schedule = TimeSchedule::factory()->create();

        $this->get(route('time-schedules.show', $schedule->id))->assertOk();
    }

    public function test_show_returns_404_for_missing_schedule(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('time-schedules.show', 9999))->assertNotFound();
    }

    public function test_edit_renders_form(): void
    {
        $this->actAsSuperAdmin();
        $schedule = TimeSchedule::factory()->create();

        $this->get(route('time-schedules.edit', $schedule->id))->assertOk();
    }

    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $schedule = TimeSchedule::factory()->create(['name' => 'Original']);

        $this->put(route('time-schedules.update', $schedule->id), [
            'name' => 'Updated Schedule',
            'in_time' => '09:00',
            'out_time' => '17:00',
            'is_multi_day' => false,
            'late_margin' => 15,
            'early_margin' => 10,
            'breaks' => [],
        ])->assertRedirect(route('time-schedules.index'));

        $this->assertDatabaseHas('att_time_schedules', [
            'id' => $schedule->id,
            'name' => 'Updated Schedule',
        ]);
    }

    public function test_destroy_deletes_schedule(): void
    {
        $this->actAsSuperAdmin();
        $schedule = TimeSchedule::factory()->create();

        $this->delete(route('time-schedules.destroy', $schedule->id))
            ->assertRedirect(route('time-schedules.index'));

        $this->assertDatabaseMissing('att_time_schedules', ['id' => $schedule->id]);
    }

    public function test_copy_duplicates_schedule(): void
    {
        $this->actAsSuperAdmin();
        $schedule = TimeSchedule::factory()->create(['name' => 'Original']);

        $this->post(route('time-schedules.copy', $schedule->id), [
            'name' => 'Copied Schedule',
        ])->assertRedirect(route('time-schedules.index'));

        $this->assertDatabaseHas('att_time_schedules', ['name' => 'Copied Schedule']);
    }

    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('time-schedules.index'))->assertRedirect();
    }
}
