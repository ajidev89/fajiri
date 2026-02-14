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
        if (Role::count() != 0) {
            return;
        }


        $roles = [
            'Super Admin',
            'Admin',
            'User'
        ];

        foreach ($roles as $key => $role) {
            Role::create([
                'name' => $role
            ]);
        }
    }
}
