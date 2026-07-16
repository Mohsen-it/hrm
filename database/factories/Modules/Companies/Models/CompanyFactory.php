<?php

namespace Database\Factories\Modules\Companies\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Companies\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Company>
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'company_code' => strtoupper(Str::random(3)).fake()->unique()->numberBetween(100, 999),
            'company_name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'SA',
            'state' => 'Riyadh',
            'postal_code' => fake()->postcode(),
            'website' => fake()->url(),
            'description' => fake()->sentence(),
            'is_default' => false,
            'status' => 1,
        ];
    }

    /**
     * Mark the company as the default one in the catalogue.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Mark the company as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
