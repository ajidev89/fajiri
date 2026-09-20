<?php

namespace Tests\Feature;

use App\Enums\Donations\Medium;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonationMediumEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_donations_accept_every_payment_medium(): void
    {
        foreach (Medium::values() as $medium) {
            $donation = Donation::create([
                'donatable_id' => (string) Str::uuid(),
                'donatable_type' => Campaign::class,
                'amount' => 4,
                'currency' => 'USD',
                'converted_amount' => 4,
                'rate' => 1,
                'medium' => $medium,
                'name' => 'Guest',
                'email' => 'hello@eei.net',
                'status' => 'pending',
                'reference' => strtoupper($medium).'_'.uniqid(),
            ]);

            $this->assertSame($medium, $donation->fresh()->medium);
        }
    }
}
