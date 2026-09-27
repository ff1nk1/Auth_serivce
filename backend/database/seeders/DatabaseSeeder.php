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

        // 2. Достаем нужную роль из базы (например, admin)
        $adminRole = Role::where('slug', 'admin')->first();

        // 3. Создаем пользователя, явно указывая существующую роль
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role_id' => $adminRole->id, // Перезаписываем поведение фабрики
        ]);
        
        $customerRole = Role::where('slug', 'customer')->first();
        User::factory(10)->create([
            'role_id' => $customerRole->id,
        ]);
    }
}