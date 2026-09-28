<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Создаем все роли
        $this->call([
            RoleSeeder::class,
        ]);

        $adminRole = Role::where('slug', 'admin')->first();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => $adminRole->id,
        ]);

        $customerRole = Role::where('slug', 'customer')->first();
        User::factory(10)->create([
            'role_id' => $customerRole->id,
        ]);
    }
}
