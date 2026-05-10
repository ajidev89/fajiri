<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find Canada
        $country = Country::where('name', 'Canada')->first();
        
        // Find User Role
        $role = Role::where('slug', 'user')->first() ?? Role::where('name', 'User')->first();

        // Create the specific user
        $user = User::updateOrCreate(
            ['email' => 'ajidagba21@yopmail.com'],
            [
                'username' => 'ajidev_canada',
                'password' => Hash::make('password'), // Default password
                'country_id' => $country?->id,
                'role_id' => $role?->id,
                'account_type' => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        // Ensure user has a profile
        if (!$user->profile) {
            $user->profile()->create([
                'first_name' => 'Aji',
                'last_name' => 'Canada',
            ]);
        }
        
        // Ensure user has a wallet with CAD if in Canada
        if (!$user->wallet) {
            $user->wallet()->create([
                'balance' => 0,
                'currency' => $country?->currency ?? 'CAD',
            ]);
        }
    }
}
