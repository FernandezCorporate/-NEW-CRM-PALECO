<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'name_ext' => null,
            'email' => fake()->unique()->safeEmail(),
            'contact' => '09'.fake()->numerify('#########'),
            'role_id' => 1,
            'department_id' => null,
            'password' => static::$password ??= Hash::make('Password'),
            'is_active' => true,
            'locked_until' => null,
            'last_login' => null,
        ];
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => 1,
            'department_id' => null,
        ]);
    }

    /**
     * Indicate that the user is a CWD Officer.
     */
    public function cwdOfficer(): static
    {
        return $this->state(fn () => [
            'role_id' => 2,
            'department_id' => null,
        ]);
    }

    /**
     * Indicate that the user is a Supervisor.
     */
    public function supervisor(?int $departmentId = null): static
    {
        return $this->state(fn () => [
            'role_id' => 3,
            'department_id' => $departmentId ?? Department::first()?->id ?? Department::factory(),
        ]);
    }

    /**
     * Indicate that the user is Field Personnel.
     */
    public function fieldPersonnel(): static
    {
        return $this->state(fn () => [
            'role_id' => 4,
            'department_id' => null,
        ]);
    }

    /**
     * Indicate that the user is deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user is locked out.
     */
    public function locked(): static
    {
        return $this->state(fn () => [
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
