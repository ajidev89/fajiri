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

        Config::set('flutterwave.clientId', 'test_client_id_123');
        Config::set('flutterwave.clientSecret', 'test_client_secret_456');
        Config::set('flutterwave.secretHash', 'test_webhook_hash_secret');
        Config::set('flutterwave.version', 'v4');
        Config::set('flutterwave.paymentUrl', 'https://developersandbox-api.flutterwave.com');

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

    public function test_flutterwave_v4_webhook_successfully_credits_user_wallet(): void
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
            'id'        => 'wbk_998877',
            'type'      => 'charge.completed',
            'timestamp' => 1735116884019,
            'data'      => [
                'id'        => 'chg_webhook_123',
                'status'    => 'succeeded',
                'amount'    => 5000.0,
                'currency'  => 'NGN',
                'reference' => 'ref_wf_test_001',
                'customer'  => [
                    'email' => 'flw_user@example.com',
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = base64_encode(hash_hmac('sha256', $rawBody, 'test_webhook_hash_secret', true));

        // Mock verification request
        Http::fake([
            'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token' => Http::response([
                'access_token' => 'flw_token_123',
                'expires_in'   => 600,
            ], 200),
            'https://developersandbox-api.flutterwave.com/charges/chg_webhook_123' => Http::response([
                'status' => 'success',
                'data'   => [
                    'id'        => 'chg_webhook_123',
                    'status'    => 'succeeded',
                    'amount'    => 5000.0,
                    'currency'  => 'NGN',
                    'reference' => 'ref_wf_test_001',
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
                'HTTP_FLUTTERWAVE_SIGNATURE' => $signature,
                'CONTENT_TYPE'               => 'application/json',
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
                'HTTP_FLUTTERWAVE_SIGNATURE' => 'invalid_signature_hash',
                'CONTENT_TYPE'               => 'application/json',
            ],
            json_encode(['test' => 'data'])
        );

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Invalid signature']);
    }
}
