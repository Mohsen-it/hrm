<?php

namespace Database\Factories\Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\Shift;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Shift>
     */
    protected $model = Shift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'shift_code' => strtoupper(Str::random(3)).fake()->unique()->numberBetween(100, 999),
            'shift_name' => fake()->randomElement(['Morning', 'Evening', 'Night']).' Shift',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'break_minutes' => 60,
            'grace_minutes' => 15,
            'working_hours' => 7.0,
            'work_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'],
            'description' => fake()->sentence(),
            'status' => 1,
        ];
    }
}
