<?php

namespace Database\Factories\Modules\Shifts\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Companies\Models\Company;
use Modules\Shifts\Models\TimeSchedule;

/**
 * @extends Factory<TimeSchedule>
 */
class TimeScheduleFactory extends Factory
{
    protected $model = TimeSchedule::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->randomElement(['Schedule A', 'Schedule B', 'Schedule C', 'Night Schedule', 'Day Schedule']),
            'in_time' => '08:00:00',
            'out_time' => '16:00:00',
            'is_multi_day' => false,
            'late_margin' => 15,
            'early_margin' => 15,
        ];
    }
}
