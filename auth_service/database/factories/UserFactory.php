<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'role_id' => 1,
            'number' => $this->faker->phoneNumber(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => 2,
        ]);
    }
}
