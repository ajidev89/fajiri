<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $response = Http::get('https://countriesnow.space/api/v0.1/countries/states');
            $response2 = Http::get('https://countriesnow.space/api/v0.1/countries/currency');
            $response3 = Http::get('https://countriesnow.space/api/v0.1/countries/codes');

            if ($response->successful() && $response2->successful() && $response3->successful()) {
                $countries = json_decode($response->body(),true);
                $currencies = json_decode($response2->body(),true);
                $phoneCodes = json_decode($response3->body(),true);

                foreach ( $countries['data'] as $key => $item) {

                    $currency =collect($currencies['data'])->firstWhere('iso3', $item['iso3']);
                    $phone = collect($phoneCodes['data'])->firstWhere('code', $item['iso2']);

                    Country::create([
                        'name' => $item['name'],
                        'iso3' => $item['iso3'],
                        'iso2' => $item['iso2'],
                        'currency' => $currency['currency'] ?? null,
                        'phone_code' => $phone['dial_code'] ?? null,
                    ]);
                }

            } else {
                throw new \Exception($response->body());
            }

        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
