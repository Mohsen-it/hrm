<?php

namespace Database\Factories\Modules\Departments\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;
use Modules\Departments\Models\Department;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Department>
     */
    protected $model = Department::class;

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
            'department_code' => strtoupper(Str::random(3)).fake()->unique()->numberBetween(100, 999),
            'department_name' => 'قسم '.fake()->word(),
            'description' => fake()->sentence(),
            'status' => 1,
        ];
    }
}
