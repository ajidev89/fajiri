<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlutterwaveWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('flutterwave.publicKey', 'FLWPUBK_TEST-123456789-X');
        Config::set('flutterwave.secretKey', 'FLWSECK_TEST-987654321-X');
        Config::set('flutterwave.secretHash', 'test_webhook_hash_secret');
        Config::set('flutterwave.paymentUrl', 'https://api.flutterwave.com/v3');

        Country::create([
            'id'         => 1,
            'name'       => 'Nigeria',
            'iso3'       => 'NGA',
            'iso2'       => 'NG',
            'currency'   => 'NGN',
            'phone_code' => '+234',
        ]);

        Role::create([
            'id'   => 1,
            'name' => 'Member',
            'slug' => 'member',
        ]);
    }

    public function test_flutterwave_v3_webhook_successfully_credits_user_wallet(): void
    {
        $user = User::create([
            'email'             => 'flw_user@example.com',
            'phone'             => '+2348100000001',
            'country_id'        => 1,
            'password'          => Hash::make('secret123'),
            'role_id'           => 1,
            'account_type'      => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'member_id'         => 'FAJ-TEST01',
        ]);

        $user->wallet()->create([
            'balance' => 1000.00,
        ]);

        $payload = [
            'event' => 'charge.completed',
            'data'  => [
                'id'        => 2882001,
                'status'    => 'successful',
                'amount'    => 5000.0,
                'currency'  => 'NGN',
                'tx_ref'    => 'ref_wf_test_001',
                'customer'  => [
                    'email' => 'flw_user@example.com',
                ],
            ],
        ];

        $rawBody = json_encode($payload);

        // Mock verification request
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/2882001/verify' => Http::response([
                'status' => 'success',
                'data'   => [
                    'id'        => 2882001,
                    'status'    => 'successful',
                    'amount'    => 5000.0,
                    'currency'  => 'NGN',
                    'tx_ref'    => 'ref_wf_test_001',
                    'customer'  => [
                        'email' => 'flw_user@example.com',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->call(
            'POST',
            '/v1/webhooks/flutterwave',
            [],
            [],
            [],
            [
                'HTTP_VERIF_HASH' => 'test_webhook_hash_secret',
                'CONTENT_TYPE'    => 'application/json',
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Flutterwave webhook processed']);

        $user->refresh();
        $this->assertEquals(6000.00, (float) $user->wallet->balance);
    }

    public function test_flutterwave_webhook_rejects_invalid_signature(): void
    {
        $response = $this->call(
            'POST',
            '/v1/webhooks/flutterwave',
            [],
            [],
            [],
            [
                'HTTP_VERIF_HASH' => 'invalid_signature_hash',
                'CONTENT_TYPE'    => 'application/json',
            ],
            json_encode(['test' => 'data'])
        );

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Invalid signature']);
    }
}
