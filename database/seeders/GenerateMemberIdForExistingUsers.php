<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GenerateMemberIdForExistingUsers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = \App\Models\User::whereNull('member_id')->get();

        foreach ($users as $user) {
            $user->member_id = \App\Models\User::generateUniqueMemberId($user->account_type);
            $user->save();
        }

        if (app()->runningInConsole()) {
            echo "Generated member IDs for {$users->count()} users.\n";
        }
    }
}
