<?php

namespace Database\Factories\Modules\Branches\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Branches\Models\Branch;
use Modules\Companies\Models\Company;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Branch>
     */
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_code' => strtoupper(Str::random(3)).fake()->unique()->numberBetween(100, 999),
            'branch_name' => fake()->company().' Branch',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'SA',
            'manager_name' => fake()->name(),
            'manager_phone' => fake()->phoneNumber(),
            'description' => fake()->sentence(),
            'is_main' => false,
            'status' => 1,
        ];
    }

    /**
     * Mark the branch as the main one for its company.
     */
    public function main(): static
    {
        return $this->state(fn () => ['is_main' => true]);
    }

    /**
     * Mark the branch as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
