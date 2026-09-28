<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'), // Дефолтный пароль для тестов
            'role_id' => 1,                          // Базовая роль (например, обычный пользователь)
            'number' => $this->faker->phoneNumber(), // Телефон или другой номер
        ];
    }

    /**
     * Состояние для создания пользователя с роу админа
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => 2,
        ]);
    }
}
