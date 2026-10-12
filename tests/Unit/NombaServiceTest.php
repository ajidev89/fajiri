<?php

namespace Tests\Unit;

use App\Services\NombaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NombaServiceTest extends TestCase
{
    public function test_it_issues_a_token_from_the_current_nomba_auth_endpoint(): void
    {
        Cache::flush();

        config([
            'nomba.mode' => 'test',
            'nomba.baseUrl' => 'https://sandbox.nomba.com',
            'nomba.clientId' => 'test-client',
            'nomba.clientSecret' => 'test-secret',
            'nomba.accountId' => 'acc-123',
        ]);

        Http::fake([
            'https://sandbox.nomba.com/v1/auth/token/issue' => Http::response([
                'code' => '00',
                'description' => 'Success',
                'data' => [
                    'access_token' => 'nomba-access-token',
                ],
            ], 200),
        ]);

        $token = app(NombaService::class)->getAccessToken();

        $this->assertSame('nomba-access-token', $token);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.nomba.com/v1/auth/token/issue'
                && $request->header('accountId')[0] === 'acc-123'
                && $request['grant_type'] === 'client_credentials'
                && $request['client_id'] === 'test-client';
        });
    }

    public function test_it_creates_a_sandbox_checkout_order(): void
    {
        Cache::flush();

        config([
            'nomba.mode' => 'test',
            'nomba.baseUrl' => 'https://sandbox.nomba.com',
            'nomba.clientId' => 'test-client',
            'nomba.clientSecret' => 'test-secret',
            'nomba.accountId' => 'acc-123',
        ]);

        Http::fake([
            'https://sandbox.nomba.com/v1/auth/token/issue' => Http::response([
                'code' => '00',
                'data' => ['access_token' => 'nomba-access-token'],
            ], 200),
            'https://sandbox.nomba.com/sandbox/checkout/order' => Http::response([
                'code' => '00',
                'data' => [
                    'checkoutLink' => 'https://checkout.nomba.com/sandbox/abc',
                    'orderReference' => 'NMB_ref',
                ],
            ], 200),
        ]);

        $result = app(NombaService::class)->createCheckoutOrder([
            'amount' => 5000,
            'currency' => 'NGN',
            'email' => 'guest@example.com',
            'reference' => 'NMB_ref',
        ]);

        $this->assertSame('https://checkout.nomba.com/sandbox/abc', $result['checkoutLink']);
        $this->assertSame('NMB_ref', $result['orderReference']);
    }

    public function test_it_shortens_order_references_longer_than_fifty_characters(): void
    {
        Cache::flush();

        config([
            'nomba.mode' => 'test',
            'nomba.baseUrl' => 'https://sandbox.nomba.com',
            'nomba.clientId' => 'test-client',
            'nomba.clientSecret' => 'test-secret',
            'nomba.accountId' => 'acc-123',
        ]);

        Http::fake([
            'https://sandbox.nomba.com/v1/auth/token/issue' => Http::response([
                'code' => '00',
                'data' => ['access_token' => 'nomba-access-token'],
            ], 200),
            'https://sandbox.nomba.com/sandbox/checkout/order' => Http::response([
                'code' => '00',
                'data' => [
                    'checkoutLink' => 'https://checkout.nomba.com/sandbox/abc',
                    'orderReference' => 'nomba_short',
                ],
            ], 200),
        ]);

        $longReference = 'nomba_sub_019f7a87-1f71-721f-a12e-28c9fdddbace_019f7a87-1f71-721f-a12e-28c9fdddbace_'.time();

        app(NombaService::class)->createCheckoutOrder([
            'amount' => 5000,
            'currency' => 'NGN',
            'email' => 'guest@example.com',
            'reference' => $longReference,
            'metadata' => [
                'user_id' => '019f7a87-1f71-721f-a12e-28c9fdddbace',
                'plan_id' => '019f7a87-1f71-721f-a12e-28c9fdddbace',
                'type' => 'subscription',
            ],
        ]);

        Http::assertSent(function ($request) {
            $reference = $request['order']['orderReference'] ?? null;

            return $request->url() === 'https://sandbox.nomba.com/sandbox/checkout/order'
                && is_string($reference)
                && strlen($reference) <= 50
                && $request['order']['orderMetaData']['type'] === 'subscription';
        });
    }

    public function test_it_reports_nomba_error_description_when_the_ip_is_rejected(): void
    {
        Cache::flush();

        config([
            'nomba.mode' => 'live',
            'nomba.baseUrl' => 'https://api.nomba.com',
            'nomba.clientId' => 'live-client',
            'nomba.clientSecret' => 'live-secret',
            'nomba.accountId' => 'acc-123',
        ]);

        Http::fake([
            'https://api.nomba.com/v1/auth/token/issue' => Http::response([
                'code' => '403',
                'description' => 'IP address not whitelisted',
            ], 403),
        ]);

        $this->expectExceptionMessage('Nomba authentication failed (403): IP address not whitelisted');

        app(NombaService::class)->getAccessToken();
    }
}
