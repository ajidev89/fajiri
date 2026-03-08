<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class DefaultUserCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure Nigeria exists and get its ID
        $nigeria = Country::where('name', 'Nigeria')->first();

        if (!$nigeria) {
            $this->command->error("Nigeria not found in countries table. Please run CountrySeeder first.");
            return;
        }

        // 2. Find users without a country
        $usersWithoutCountry = User::whereNull('country_id')->get();

        if ($usersWithoutCountry->isEmpty()) {
            $this->command->info("No users found without a country.");
            return;
        }

        $this->command->info("Updating " . $usersWithoutCountry->count() . " users to Nigeria...");

        foreach ($usersWithoutCountry as $user) {
            // Update user country
            $user->update(['country_id' => $nigeria->id]);

            // Update or create wallet with NGN currency
            $user->wallet()->updateOrCreate(
                ['user_id' => $user->id],
                ['currency' => 'NGN']
            );
        }

        $this->command->info("Successfully updated users and wallets.");
    }
}
