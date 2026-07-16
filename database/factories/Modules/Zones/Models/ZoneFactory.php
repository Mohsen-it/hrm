<?php

namespace Database\Factories\Modules\Zones\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Companies\Models\Company;
use Modules\Zones\Enums\ZoneType;
use Modules\Zones\Models\Zone;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Zone>
     */
    protected $model = Zone::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(Str::random(3)).fake()->unique()->numberBetween(100, 999),
            'name_ar' => 'منطقة '.fake()->word(),
            'name_en' => fake()->unique()->word().' Zone',
            'zone_type' => ZoneType::Geographic->value,
            'city' => fake()->city(),
            'region' => fake()->word(),
            'country' => 'SA',
            'latitude' => fake()->latitude(20, 35),
            'longitude' => fake()->longitude(35, 55),
            'radius_meters' => 500,
            'description' => fake()->sentence(),
            'is_active' => true,
            'branches_count' => 0,
            'employees_count' => 0,
            'devices_count' => 0,
        ];
    }

    /**
     * Mark the zone as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
