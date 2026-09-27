<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Customer', 'slug' => 'customer'],
            ['name' => 'Analyst',  'slug' => 'analyst'],
            ['name' => 'Admin',    'slug' => 'admin'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['slug' => $role['slug']], 
                ['name' => $role['name']]
            );
        }
    }
}