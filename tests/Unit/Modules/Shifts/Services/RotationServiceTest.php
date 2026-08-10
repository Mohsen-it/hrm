<?php

namespace Tests\Unit\Modules\Shifts\Services;

use Illuminate\Validation\ValidationException;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Rotation;
use Modules\Shifts\Models\RotationAssignment;
use Modules\Shifts\Models\RotationGroup;
use Modules\Shifts\Services\RotationService;
use Modules\Users\Models\User;
use Tests\TestCase;

class RotationServiceTest extends TestCase
{
    private RotationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RotationService::class);
    }

    /**
     * Re-assigning an employee must never be blocked by an old assignment:
     * the old row stays in the database but is closed the day before the new
     * start date (mirroring how transfers close the current assignment).
     */
    public function test_assigning_an_employee_with_an_overlapping_assignment_closes_the_old_one(): void
    {
        $user = $this->makeEmployee('EMP10001');
        [$rotation, $groupA, $groupB] = $this->makeRotation($user->company_id);

        $old = RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $groupA->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
        ]);

        $this->service->assignEmployee(
            $user->id,
            $rotation->id,
            $groupB->id,
            '2026-08-01',
        );

        $old->refresh();

        $this->assertNotNull($old->end_date, 'The old assignment must be closed, not deleted.');
        $this->assertSame('2026-07-31', $old->end_date->toDateString());

        $new = RotationAssignment::where('employee_id', $user->id)
            ->where('id', '!=', $old->id)
            ->first();

        $this->assertNotNull($new, 'A new assignment row must be created.');
        $this->assertSame($groupB->id, $new->rotation_group_id);
        $this->assertSame('2026-08-01', $new->start_date->toDateString());
        $this->assertNull($new->end_date);
    }

    /**
     * When the requested period starts before the previous assignment began,
     * closing the old row would corrupt its date range, so the assignment is
     * rejected with the conflict validation error.
     */
    public function test_assigning_a_period_starting_before_the_old_assignment_still_conflicts(): void
    {
        $user = $this->makeEmployee('EMP10002');
        [$rotation, $groupA, $groupB] = $this->makeRotation($user->company_id);

        RotationAssignment::create([
            'employee_id' => $user->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $groupA->id,
            'start_date' => '2026-08-15',
            'end_date' => null,
        ]);

        // The service translates the message at throw time (locale dependent),
        // so assert on the validation key instead of the translated text.
        try {
            $this->service->assignEmployee(
                $user->id,
                $rotation->id,
                $groupB->id,
                '2026-08-01',
            );
            $this->fail('Expected a ValidationException for the assignment conflict.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('employee_id', $e->errors());
        }
    }

    /**
     * Bulk assignment closes the previous assignment for every employee, and
     * leaves a second open row behind for each of them.
     */
    public function test_bulk_assign_closes_overlapping_assignments_for_each_employee(): void
    {
        $userA = $this->makeEmployee('EMP10003');
        $userB = $this->makeEmployee('EMP10004');
        [$rotation, $groupA, $groupB] = $this->makeRotation($userA->company_id);

        $oldA = RotationAssignment::create([
            'employee_id' => $userA->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $groupA->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
        ]);
        $oldB = RotationAssignment::create([
            'employee_id' => $userB->id,
            'rotation_id' => $rotation->id,
            'rotation_group_id' => $groupA->id,
            'start_date' => '2026-07-01',
            'end_date' => null,
        ]);

        $assignments = $this->service->assignEmployees(
            [$userA->id, $userB->id],
            $rotation->id,
            $groupB->id,
            '2026-08-01',
        );

        $this->assertCount(2, $assignments);

        foreach ([$oldA, $oldB] as $old) {
            $old->refresh();
            $this->assertSame('2026-07-31', $old->end_date->toDateString());
        }

        $this->assertSame(
            2,
            RotationAssignment::whereIn('employee_id', [$userA->id, $userB->id])
                ->where('id', '!=', $oldA->id)
                ->where('id', '!=', $oldB->id)
                ->whereNull('end_date')
                ->count(),
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

    /**
     * @return array{0: Rotation, 1: RotationGroup, 2: RotationGroup}
     */
    private function makeRotation(int $companyId): array
    {
        $rotation = Rotation::create([
            'company_id' => $companyId,
            'name' => 'Test Rotation',
            'anchor_start_date' => '2026-07-01',
            'pattern' => [1, 0, 0, 0],
            'cycle_length' => 4,
            'work_days_count' => 1,
            'rest_days_count' => 3,
            'number_of_groups' => 2,
            'grace_minutes' => 0,
        ]);

        $groupA = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'A',
            'group_index' => 0,
        ]);
        $groupB = RotationGroup::create([
            'rotation_id' => $rotation->id,
            'name' => 'B',
            'group_index' => 1,
        ]);

        return [$rotation, $groupA, $groupB];
    }
}
