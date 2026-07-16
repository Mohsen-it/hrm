<?php

namespace Database\Factories\Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\ShiftCategory;

/**
 * @extends Factory<ShiftCategory>
 */
class ShiftCategoryFactory extends Factory
{
    protected $model = ShiftCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->randomElement(['Morning A', 'Evening B', 'Night C', 'Cyclic 3+1', 'Weekly Fixed', 'Hours Based']),
            'type' => fake()->randomElement(['cyclic', 'weekly', 'hours']),
            'work_days' => 3,
            'rest_days' => 1,
            'work_days_json' => [0, 1, 2, 3, 4],
            'weekend_days_json' => [5, 6],
            'required_hours' => 40.00,
            'period_type' => 'weekly',
            'overtime_enabled' => false,
            'fingerprint_enabled' => true,
            'work_on_holidays' => false,
            'work_on_weekends' => false,
            'color' => '#fa520f',
        ];
    }

    public function cyclic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cyclic',
            'work_days' => 3,
            'rest_days' => 1,
            'work_days_json' => null,
            'weekend_days_json' => null,
            'required_hours' => null,
            'period_type' => null,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'weekly',
            'work_days' => null,
            'rest_days' => null,
            'work_days_json' => [0, 1, 2, 3, 4],
            'weekend_days_json' => [5, 6],
            'required_hours' => null,
            'period_type' => null,
        ]);
    }

    public function hours(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'hours',
            'work_days' => null,
            'rest_days' => null,
            'work_days_json' => null,
            'weekend_days_json' => null,
            'required_hours' => 40.00,
            'period_type' => 'weekly',
        ]);
    }
}
