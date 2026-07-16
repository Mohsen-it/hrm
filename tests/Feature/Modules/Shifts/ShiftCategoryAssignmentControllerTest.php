<?php

namespace Tests\Feature\Modules\Shifts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;
use Modules\Shifts\Http\Controllers\ShiftCategoryAssignmentController;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Feature coverage for {@see ShiftCategoryAssignmentController}.
 */
class ShiftCategoryAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_successful_response_for_authorized_user(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-assignments.index'))->assertOk();
    }

    public function test_assign_page_renders_form(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-assignments.assign'))->assertOk();
    }

    public function test_assign_page_passes_preselected_category(): void
    {
        $this->actAsSuperAdmin();
        $category = ShiftCategory::factory()->create();

        $response = $this->get(route('shift-assignments.assign', ['category' => $category->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('preselected_category_id', $category->id)
        );
    }

    public function test_bulk_assign_page_renders_form(): void
    {
        $this->actAsSuperAdmin();

        $this->get(route('shift-assignments.bulk-assign'))->assertOk();
    }

    public function test_assign_persists_assignment_and_redirects(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        $response = $this->post(route('shift-assignments.assign'), [
            'employee_id' => $user->id,
            'shift_category_id' => $category->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ]);

        $response->assertRedirect(route('shift-assignments.index'));
        $this->assertDatabaseHas('att_employee_shift_categories', [
            'employee_id' => $user->id,
            'shift_category_id' => $category->id,
            'end_date' => null,
        ]);
    }

    public function test_assign_closes_previous_active_assignment(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category1 = ShiftCategory::factory()->create();
        $category2 = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        $this->post(route('shift-assignments.assign'), [
            'employee_id' => $user->id,
            'shift_category_id' => $category1->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ]);

        $this->post(route('shift-assignments.assign'), [
            'employee_id' => $user->id,
            'shift_category_id' => $category2->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ]);

        $previous = EmployeeShiftCategory::where('employee_id', $user->id)
            ->where('shift_category_id', $category1->id)
            ->first();

        $this->assertNotNull($previous);
        $this->assertNotNull($previous->end_date);
        $this->assertEquals(now()->subDay()->toDateString(), $previous->end_date->toDateString());

        $current = EmployeeShiftCategory::where('employee_id', $user->id)
            ->where('shift_category_id', $category2->id)
            ->first();

        $this->assertNotNull($current);
        $this->assertNull($current->end_date);
    }

    public function test_bulk_assign_assigns_multiple_employees(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category = ShiftCategory::factory()->create();
        $user1 = $this->createEmployee();
        $user2 = $this->createEmployee();
        $user3 = $this->createEmployee();

        $response = $this->post(route('shift-assignments.bulk-assign'), [
            'employee_ids' => [$user1->id, $user2->id, $user3->id],
            'shift_category_id' => $category->id,
            'start_date' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('shift-assignments.index'));
        $this->assertDatabaseHas('att_employee_shift_categories', ['employee_id' => $user1->id, 'shift_category_id' => $category->id]);
        $this->assertDatabaseHas('att_employee_shift_categories', ['employee_id' => $user2->id, 'shift_category_id' => $category->id]);
        $this->assertDatabaseHas('att_employee_shift_categories', ['employee_id' => $user3->id, 'shift_category_id' => $category->id]);
    }

    public function test_transfer_moves_employee_to_new_category(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category1 = ShiftCategory::factory()->create();
        $category2 = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        $this->post(route('shift-assignments.assign'), [
            'employee_id' => $user->id,
            'shift_category_id' => $category1->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => null,
        ]);

        $this->post(route('shift-assignments.transfer'), [
            'employee_id' => $user->id,
            'new_category_id' => $category2->id,
            'effective_date' => now()->toDateString(),
        ]);

        $current = EmployeeShiftCategory::where('employee_id', $user->id)
            ->where('shift_category_id', $category2->id)
            ->first();

        $this->assertNotNull($current);
        $this->assertNull($current->end_date);

        $previous = EmployeeShiftCategory::where('employee_id', $user->id)
            ->where('shift_category_id', $category1->id)
            ->first();

        $this->assertNotNull($previous);
        $this->assertNotNull($previous->end_date);
        $this->assertEquals(now()->subDay()->toDateString(), $previous->end_date->toDateString());
    }

    public function test_unassign_closes_active_assignment(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        $this->post(route('shift-assignments.assign'), [
            'employee_id' => $user->id,
            'shift_category_id' => $category->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => null,
        ]);

        $this->post(route('shift-assignments.unassign'), [
            'employee_id' => $user->id,
        ])->assertRedirect(route('shift-assignments.index'));

        $assignment = EmployeeShiftCategory::where('employee_id', $user->id)
            ->where('shift_category_id', $category->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertNotNull($assignment->end_date);
        $this->assertEquals(now()->toDateString(), $assignment->end_date->toDateString());
    }

    public function test_unassign_returns_404_for_no_active_assignment(): void
    {
        $this->actAsSuperAdmin();
        $user = $this->createEmployee();

        $this->post(route('shift-assignments.unassign'), [
            'employee_id' => $user->id,
        ])->assertNotFound();
    }

    public function test_search_employees_returns_json(): void
    {
        $this->actAsSuperAdmin();
        $user = $this->createEmployee(['name' => 'Ahmed Test']);

        $response = $this->get(route('shift-assignments.search-employees', ['search' => 'Ahmed']));

        $response->assertOk();
        $response->assertJsonStructure(['employees']);
    }

    public function test_search_employees_filters_by_department(): void
    {
        $this->actAsSuperAdmin();
        $department = Department::factory()->create();
        $user1 = $this->createEmployee(['name' => 'DeptUser1', 'department_id' => $department->id]);
        $user2 = $this->createEmployee(['name' => 'DeptUser2']);

        $response = $this->get(route('shift-assignments.search-employees', [
            'search' => 'Dept',
            'department_id' => $department->id,
        ]));

        $response->assertOk();
        $data = $response->json();
        $employeeIds = collect($data['employees'])->pluck('id');
        $this->assertTrue($employeeIds->contains($user1->id));
        $this->assertFalse($employeeIds->contains($user2->id));
    }

    public function test_index_filters_by_category_id(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category1 = ShiftCategory::factory()->create();
        $category2 = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        EmployeeShiftCategory::create([
            'employee_id' => $user->id,
            'shift_category_id' => $category1->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'snapshot_data' => '{}',
        ]);

        EmployeeShiftCategory::create([
            'employee_id' => $user->id,
            'shift_category_id' => $category2->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(5)->toDateString(),
            'snapshot_data' => '{}',
        ]);

        $response = $this->get(route('shift-assignments.index', ['category_id' => $category1->id]));

        $response->assertOk();
    }

    public function test_index_filters_by_status(): void
    {
        $this->actAsSuperAdmin();
        Company::factory()->create(['status' => 1]);
        $category = ShiftCategory::factory()->create();
        $user = $this->createEmployee();

        EmployeeShiftCategory::create([
            'employee_id' => $user->id,
            'shift_category_id' => $category->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'snapshot_data' => '{}',
        ]);

        $response = $this->get(route('shift-assignments.index', ['status' => 'active']));

        $response->assertOk();
    }

    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('shift-assignments.index'))->assertRedirect();
    }

    private function createEmployee(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 1,
            'is_active_employee' => true,
            'name' => 'Test Employee',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'employee_code' => 'EMP'.fake()->unique()->numberBetween(10000, 99999),
        ], $overrides));
    }
}
