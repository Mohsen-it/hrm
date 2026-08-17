<?php

namespace Tests\Feature\Modules\Shifts;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;
use Modules\Shifts\Http\Controllers\UnassignedRotationEmployeesController;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Users\Models\User;
use Tests\TestCase;

/**
 * Feature coverage for {@see UnassignedRotationEmployeesController}.
 */
class UnassignedRotationEmployeesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_active_employees_without_rotation(): void
    {
        $this->actAsSuperAdmin();

        $unassignedActive = $this->createEmployee();
        $assigned = $this->createEmployee(); // open assignment → must be excluded
        $closedAssignment = $this->createEmployee(); // assignment ended → must appear
        User::factory()->inactive()->create(); // inactive without rotation → must be excluded

        $this->createRotationAssignment($assigned, null);
        $this->createRotationAssignment($closedAssignment, now()->subDay()->toDateString());

        $response = $this->get(route('rotations.unassigned-employees'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total', 2)
            ->has('employees.data', 2)
            // Ordered by id desc: the closed-assignment employee was created last.
            ->where('employees.data.0.id', $closedAssignment->id)
            ->where('employees.data.1.id', $unassignedActive->id)
        );
    }

    public function test_index_filters_by_department(): void
    {
        $this->actAsSuperAdmin();
        $department = Department::factory()->create();
        $inDept = $this->createEmployee(['department_id' => $department->id]);
        $this->createEmployee();

        $response = $this->get(route('rotations.unassigned-employees', [
            'department_id' => $department->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total', 1)
            ->has('employees.data', 1)
            ->where('employees.data.0.id', $inDept->id)
        );
    }

    public function test_export_returns_excel_file(): void
    {
        $this->actAsSuperAdmin();
        $this->createEmployee();

        $response = $this->get(route('rotations.unassigned-employees.export'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'unassigned-rotation-employees',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_unauthenticated_visitors_are_redirected(): void
    {
        $this->seedPermissions();

        $this->get(route('rotations.unassigned-employees'))->assertRedirect();
        $this->get(route('rotations.unassigned-employees.export'))->assertRedirect();
    }

    private function createEmployee(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'status' => 1,
            'is_active_employee' => true,
            'employee_code' => 'EMP'.fake()->unique()->numberBetween(10000, 99999),
        ], $overrides));
    }

    private function createRotationAssignment(User $employee, ?string $endDate): void
    {
        $company = Company::factory()->create(['status' => 1]);
        $rotation = Rotation::create([
            'company_id' => $company->id,
            'name' => 'Test Rotation '.uniqid(),
            'anchor_start_date' => now()->subDays(30)->toDateString(),
            'pattern' => [1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0],
            'cycle_length' => 12,
            'work_days_count' => 3,
            'rest_days_count' => 9,
            'number_of_groups' => 4,
        ]);
        $group = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
        ]);

        RotationAssignment::create([
            'employee_id' => $employee->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $group->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => $endDate,
            'snapshot_data' => json_encode(['rotation' => [], 'group' => []]),
        ]);
    }
}
