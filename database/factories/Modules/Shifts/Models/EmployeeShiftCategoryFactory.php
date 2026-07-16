<?php

namespace Database\Factories\Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shifts\Models\EmployeeShiftCategory;
use Modules\Shifts\Models\ShiftCategory;
use Modules\Users\Models\User;

/**
 * @extends Factory<EmployeeShiftCategory>
 */
class EmployeeShiftCategoryFactory extends Factory
{
    protected $model = EmployeeShiftCategory::class;

    public function definition(): array
    {
        return [
            'employee_id' => User::factory(),
            'shift_category_id' => ShiftCategory::factory(),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'snapshot_data' => json_encode(['category' => [], 'time_schedule' => null]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'end_date' => now()->subDay()->toDateString(),
        ]);
    }
}
