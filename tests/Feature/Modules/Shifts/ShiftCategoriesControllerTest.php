<?php

namespace Tests\Feature\Modules\Shifts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Models\Company;
use Modules\Shifts\Http\Controllers\ShiftCategoriesController;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Feature coverage for {@see ShiftCategoriesController}.
 */
class ShiftCategoriesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_successful_response_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-categories.index'))->assertOk();
    }

    public function test_create_renders_form_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-categories.create'))->assertOk();
    }

    public function test_store_persists_cyclic_category_and_redirects(): void
    {
        $this->actAsSuperAdmin();
        $company = Company::factory()->create(['status' => 1]);

        $payload = [
            'name' => 'Cyclic Test',
            'type' => 'cyclic',
            'work_days' => 3,
            'rest_days' => 1,
            'overtime_enabled' => false,
            'fingerprint_enabled' => true,
            'work_on_holidays' => false,
            'work_on_weekends' => false,
            'color' => '#fa520f',
        ];

        $response = $this->post(route('shift-categories.store'), $payload);

        $response->assertRedirect(route('shift-categories.index'));
        $this->assertDatabaseHas('att_shift_categories', ['name' => 'Cyclic Test', 'type' => 'cyclic']);
    }

    public function test_store_persists_weekly_category(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);

        $payload = [
            'name' => 'Weekly Test',
            'type' => 'weekly',
            'work_days_json' => [0, 1, 2, 3, 4],
            'weekend_days_json' => [5, 6],
            'overtime_enabled' => false,
            'fingerprint_enabled' => true,
            'work_on_holidays' => false,
            'work_on_weekends' => false,
            'color' => '#fa520f',
        ];

        $this->post(route('shift-categories.store'), $payload)
            ->assertRedirect(route('shift-categories.index'));

        $this->assertDatabaseHas('att_shift_categories', ['name' => 'Weekly Test', 'type' => 'weekly']);
    }

    public function test_store_persists_hours_category(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);

        $payload = [
            'name' => 'Hours Test',
            'type' => 'hours',
            'required_hours' => 40.00,
            'period_type' => 'weekly',
            'overtime_enabled' => false,
            'fingerprint_enabled' => true,
            'work_on_holidays' => false,
            'work_on_weekends' => false,
            'color' => '#fa520f',
        ];

        $this->post(route('shift-categories.store'), $payload)
            ->assertRedirect(route('shift-categories.index'));

        $this->assertDatabaseHas('att_shift_categories', ['name' => 'Hours Test', 'type' => 'hours']);
    }

    public function test_store_rejects_cyclic_without_work_days(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);

        $this->post(route('shift-categories.store'), [
            'name' => 'Invalid Cyclic',
            'type' => 'cyclic',
            'work_days' => null,
            'rest_days' => 1,
        ])->assertSessionHasErrors('work_days');
    }

    public function test_show_renders_supplied_category(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->create();

        $this->get(route('shift-categories.show', $category->id))->assertOk();
    }

    public function test_show_returns_404_for_missing_category(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-categories.show', 9999))->assertNotFound();
    }

    public function test_edit_renders_form(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->create();

        $this->get(route('shift-categories.edit', $category->id))->assertOk();
    }

    public function test_update_persists_changes(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->cyclic()->create(['name' => 'Original']);

        $this->put(route('shift-categories.update', $category->id), [
            'name' => 'Updated Category',
            'type' => 'cyclic',
            'work_days' => 5,
            'rest_days' => 2,
            'overtime_enabled' => true,
            'fingerprint_enabled' => true,
            'work_on_holidays' => false,
            'work_on_weekends' => false,
            'color' => '#fa520f',
        ])->assertRedirect(route('shift-categories.index'));

        $this->assertDatabaseHas('att_shift_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    }

    public function test_destroy_deletes_category_without_active_employees(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->create();

        $this->delete(route('shift-categories.destroy', $category->id))
            ->assertRedirect(route('shift-categories.index'));

        $this->assertDatabaseMissing('att_shift_categories', ['id' => $category->id]);
    }

    public function test_destroy_blocked_when_category_has_active_employees(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->create();
        $user = User::factory()->create();

        EmployeeShiftCategory::create([
            'employee_id' => $user->id,
            'shift_category_id' => $category->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'snapshot_data' => '{}',
        ]);

        $this->delete(route('shift-categories.destroy', $category->id))
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('shift-categories.index'))->assertRedirect();
    }
}
