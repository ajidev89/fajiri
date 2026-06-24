<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdatePostsAndPartnersCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $nigeria = \App\Models\Country::where('name', 'Nigeria')->first();

        if ($nigeria) {
            \App\Models\Post::whereNull('country_id')->update(['country_id' => $nigeria->id]);
            \App\Models\Partner::whereNull('country_id')->update(['country_id' => $nigeria->id]);
        }
    }
}
