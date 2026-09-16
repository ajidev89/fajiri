<?php

namespace Tests\Feature;

use App\Enums\Insurance\Type;
use App\Models\Country;
use App\Models\Insurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_insurance_description_can_store_more_than_255_characters(): void
    {
        $country = Country::create([
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        $description = 'LEADWAY ASSURANCE INSURANCE BENEFITS FAJIRI, in partnership with Leadway Assurance Nigeria, provides basic life insurance, critical illness, and disability coverage for eligible Identified Membership Silver, Gold, and Platinum Family Members. Eligibility: You must reside in Nigeria or have a Nigerian government-issued ID and phone number. Coverage is subject to Leadway Assurance\'s terms, conditions, and approval.';

        $this->assertGreaterThan(255, strlen($description));

        $insurance = Insurance::create([
            'name' => 'Leadway Assurance',
            'slug' => 'leadway-assurance',
            'website' => 'https://leadway.com',
            'logo' => 'https://example.com/logo.png',
            'phone' => '+2348076185840',
            'email' => 'insure@leadway.com',
            'address' => '121/123 Funsho William Avenue, Iponri, Lagos, Surulere',
            'description' => $description,
            'type' => Type::LIFE->value,
            'city' => 'Lagos',
            'state' => 'Lagos',
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('insurances', [
            'id' => $insurance->id,
            'description' => $description,
        ]);
    }
}
