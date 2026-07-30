<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdateCorporateMemberIdPrefixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::where('member_id', 'LIKE', 'FCP%')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $newMemberId = 'FCM' . substr($user->member_id, 3);
                
                // Ensure the new member_id is unique just in case, though it should be if FCP was unique
                while (\App\Models\User::where('member_id', $newMemberId)->exists()) {
                    $newMemberId = 'FCM' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                }

                $user->update(['member_id' => $newMemberId]);
            }
        });
    }
}
