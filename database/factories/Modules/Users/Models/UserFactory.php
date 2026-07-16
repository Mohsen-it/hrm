<?php

namespace Database\Factories\Modules\Users\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Users\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model that this factory resolves to.
     *
     * Set explicitly so the framework's fallback resolver does not try
     * to map the factory basename to `App\User` (which is intentionally
     * absent from this project).
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * The factory deliberately omits the `id` field so the canonical
     * super-admin (id = 10000) remains the only fixed id in the users
     * table; the auto-increment takes over for all other employees.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'employee_code' => 'EMP'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $first.' '.$last,
            'first_name' => $first,
            'last_name' => $last,
            'full_name_ar' => $first.' '.$last,
            'full_name_en' => $first.' '.$last,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'national_id' => fake()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'employment_type' => 'full_time',
            'job_title' => fake()->jobTitle(),
            'gender' => fake()->randomElement(['male', 'female']),
            'marital_status' => 'single',
            'nationality' => 'SA',
            'status' => 1,
            'is_active_employee' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Mark the user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 0,
            'is_active_employee' => false,
        ]);
    }
}
