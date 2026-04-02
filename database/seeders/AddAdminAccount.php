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
        $email = 'admin.fajiri@yopmail.com';

        if(User::where('email', $email)->exists()){
            return;
        }

        $role = Role::where('name', 'Super Admin')->first();
        $user = User::create([
            'phone' => '+2348100000000',
            'email' => $email,            
            'country_id' => 1,
            'password' =>  Hash::make("R@ndom4Nnow"),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'role_id' => $role->id
        ]);

        $user->profile()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'gender' => 'Male',
            'date_of_birth' => '1990-01-01',
            'avatar' => 'https://ui-avatars.com/api/?name=Admin+User',
        ]);
    }
}
