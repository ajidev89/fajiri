<?php

namespace Tests\Feature;

use Tests\TestCase;

class DonationMediumsTest extends TestCase
{
    public function test_guest_can_list_donation_mediums(): void
    {
        $this->getJson('/v1/donations/mediums')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(6, 'data')
            ->assertJsonFragment(['key' => 'PAYSTACK', 'value' => 'paystack'])
            ->assertJsonFragment(['key' => 'WALLET', 'value' => 'wallet'])
            ->assertJsonFragment(['key' => 'STRIPE', 'value' => 'stripe'])
            ->assertJsonFragment(['key' => 'PAYPAL', 'value' => 'paypal'])
            ->assertJsonFragment(['key' => 'FLUTTERWAVE', 'value' => 'flutterwave'])
            ->assertJsonFragment(['key' => 'NOMBA', 'value' => 'nomba']);
    }
}
