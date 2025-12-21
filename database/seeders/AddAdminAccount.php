<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AddAdminAccount extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(User::where('email','admin@tec-ride.com')->exists()){
            return;
        }

        $role = Role::where('name', 'Super Admin')->first();
        User::create([
            'phone' => '+12345678901',
            'email' => 'admin@tec-ride.com',
            'password' =>  Hash::make("R@ndom4Nnow"),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'role_id' => $role->id
        ]);
    }
}
