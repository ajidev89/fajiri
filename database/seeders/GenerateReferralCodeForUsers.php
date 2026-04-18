<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class GenerateReferralCodeForUsers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereNull('referral_code')->get();
        
        foreach ($users as $user) {
            $user->referral_code = User::generateUniqueReferralCode();
            $user->save();
        }

        $this->command->info($users->count() . ' referral codes generated or refreshed.');
    }
}
