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
}
