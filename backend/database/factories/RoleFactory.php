<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;


class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        // Выбираем случайную роль из вашего списка
        $roleName = $this->faker->randomElement(['analyst', 'customer', 'admin']);

        return [
            'name' => $roleName,
            'slug' => Str::slug($roleName), // автоматически сделает slug (например, 'analyst')
        ];
    }
}