<?php

namespace Database\Seeders;

use App\Enums\Family\Relationship;
use App\Models\User;
use Illuminate\Database\Seeder;

class GenerateSelfFamilyMemberForUsers extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $created = 0;

        User::query()
            ->with('profile')
            ->whereDoesntHave('familyMembers', function ($query) {
                $query->where('relationship', Relationship::ME->value);
            })
            ->chunkById(100, function ($users) use (&$created) {
                foreach ($users as $user) {
                    $user->ensureSelfFamilyMember();
                    $created++;
                }
            });

        if (app()->runningInConsole()) {
            echo "Created self family members for {$created} users.\n";
        }
    }
}
