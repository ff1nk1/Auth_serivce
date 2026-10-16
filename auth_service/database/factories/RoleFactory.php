<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roleName = $this->faker->randomElement(['analyst', 'customer', 'admin']);

        return [
            'name' => $roleName,
            'slug' => Str::slug($roleName),
        ];
    }
}
