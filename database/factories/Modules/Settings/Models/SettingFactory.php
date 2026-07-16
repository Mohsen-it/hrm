<?php

namespace Database\Factories\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Settings\Models\Setting;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Setting>
     */
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'app.'.fake()->unique()->word(),
            'value' => fake()->word(),
            'type' => 'string',
            'group' => 'general',
            'name_ar' => 'إعداد '.fake()->word(),
            'name_en' => fake()->word().' Setting',
            'description' => fake()->sentence(),
            'is_public' => false,
            'is_encrypted' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Mark the setting as part of the `attendance` group.
     */
    public function inGroup(string $group): static
    {
        return $this->state(fn () => ['group' => $group]);
    }

    /**
     * Mark the setting as a public setting.
     */
    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}
