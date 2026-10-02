<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;

class NormalizeUserPhonesSeeder extends Seeder
{
    /**
     * Prefix stored user phones with their country calling code.
     */
    public function run(): void
    {
        $updated = 0;
        $skipped = 0;

        User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->with('country')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$updated, &$skipped) {
                foreach ($users as $user) {
                    $formatted = PhoneNumber::withCountryCode($user->phone, $user->country?->phone_code);

                    if ($formatted === null || $formatted === $user->phone) {
                        continue;
                    }

                    if (! str_starts_with($formatted, '+')) {
                        $skipped++;

                        continue;
                    }

                    $taken = User::query()
                        ->where('phone', $formatted)
                        ->where('id', '!=', $user->id)
                        ->exists();

                    if ($taken) {
                        $skipped++;

                        continue;
                    }

                    $user->phone = $formatted;
                    $user->saveQuietly();
                    $updated++;
                }
            });

        $this->command?->info("Updated {$updated} user phone number(s). Skipped {$skipped}.");
    }
}
