<?php

namespace Tests\Unit;

use App\Services\FlutterwaveService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FlutterwaveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('flutterwave.clientId', 'test_client_id_123');
        Config::set('flutterwave.clientSecret', 'test_client_secret_456');
        Config::set('flutterwave.secretHash', 'test_secret_hash_789');
        Config::set('flutterwave.version', 'v4');
        Config::set('flutterwave.paymentUrl', 'https://developersandbox-api.flutterwave.com');
        Config::set('flutterwave.authUrl', 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token');
    }

    public function test_oauth_access_token_retrieval_and_caching(): void
    {
        Http::fake([
            'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token' => Http::response([
                'access_token' => 'flw_access_token_mock_123',
                'expires_in'   => 600,
                'token_type'   => 'Bearer',
            ], 200),
        ]);

        $service = new FlutterwaveService();

        // 1. First call fetches token
        $token1 = $service->getAccessToken();
        $this->assertEquals('flw_access_token_mock_123', $token1);

        // 2. Second call uses in-memory cache
        $token2 = $service->getAccessToken();
        $this->assertEquals('flw_access_token_mock_123', $token2);

        Http::assertSentCount(1);
    }

    public function test_customer_payload_formatting(): void
    {
        $service = new FlutterwaveService();

        $formatted = $service->formatCustomerPayload([
            'email' => 'jane.doe@example.com',
            'name'  => 'Jane Doe',
            'phone' => '08012345678',
            'address' => [
                'line1'       => '123 Test St',
                'city'        => 'Lagos',
                'state'       => 'Lagos',
                'country'     => 'NG',
                'postal_code' => '100001',
            ],
        ]);

        $this->assertEquals('jane.doe@example.com', $formatted['email']);
        $this->assertEquals('Jane', $formatted['name']['first']);
        $this->assertEquals('Doe', $formatted['name']['last']);
        $this->assertEquals('234', $formatted['phone']['country_code']);
        $this->assertEquals('8012345678', $formatted['phone']['number']);
        $this->assertEquals('123 Test St', $formatted['address']['line1']);
        $this->assertEquals('NG', $formatted['address']['country']);
    }

    public function test_initialize_transaction_sends_v4_orchestrator_direct_orders_request(): void
    {
        Http::fake([
            'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token' => Http::response([
                'access_token' => 'flw_mock_token',
                'expires_in'   => 600,
            ], 200),
            'https://developersandbox-api.flutterwave.com/orchestration/direct-orders' => Http::response([
                'status'  => 'success',
                'message' => 'Order created',
                'data'    => [
                    'id'           => 'ord_12345',
                    'reference'    => 'test_ref_001',
                    'amount'       => 5000.0,
                    'currency'     => 'NGN',
                    'status'       => 'pending',
                    'redirect_url' => 'https://checkout.flutterwave.com/pay/ord_12345',
                    'next_action'  => [
                        'type'         => 'redirect_url',
                        'redirect_url' => [
                            'url' => 'https://checkout.flutterwave.com/pay/ord_12345',
                        ],
                    ],
                ],
            ], 201),
        ]);

        $service = new FlutterwaveService();

        $result = $service->initializeTransaction([
            'amount'       => 5000.0,
            'currency'     => 'NGN',
            'email'        => 'john@example.com',
            'name'         => 'John Doe',
            'phone'        => '08099887766',
            'reference'    => 'test_ref_001',
            'redirect_url' => 'https://fajiri.app/payments/verify/flutterwave',
        ]);

        $this->assertEquals('https://checkout.flutterwave.com/pay/ord_12345', $result['link']);
        $this->assertEquals('https://checkout.flutterwave.com/pay/ord_12345', $result['authorization_url']);
        $this->assertEquals('test_ref_001', $result['reference']);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://developersandbox-api.flutterwave.com/orchestration/direct-orders') {
                return $request->hasHeader('Authorization', 'Bearer flw_mock_token')
                    && $request->hasHeader('X-Trace-Id')
                    && $request->hasHeader('X-Idempotency-Key')
                    && $request['amount'] == 5000.0
                    && $request['currency'] === 'NGN'
                    && $request['reference'] === 'test_ref_001'
                    && $request['customer']['name']['first'] === 'John'
                    && $request['customer']['phone']['country_code'] === '234';
            }
            return true;
        });
    }

    public function test_verify_transaction_handles_v4_charges_and_normalizes_status(): void
    {
        Http::fake([
            'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token' => Http::response([
                'access_token' => 'flw_mock_token',
                'expires_in'   => 600,
            ], 200),
            'https://developersandbox-api.flutterwave.com/charges/chg_abc123' => Http::response([
                'status'  => 'success',
                'message' => 'Charge retrieved',
                'data'    => [
                    'id'        => 'chg_abc123',
                    'amount'    => 2500.0,
                    'currency'  => 'NGN',
                    'reference' => 'ref_tx_999',
                    'status'    => 'succeeded',
                    'customer'  => [
                        'email' => 'user@example.com',
                    ],
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService();
        $verified = $service->verifyTransaction('chg_abc123');

        $this->assertTrue($verified['is_successful']);
        $this->assertTrue($service->isSuccessful($verified));
        $this->assertEquals('ref_tx_999', $verified['reference']);
        $this->assertEquals('ref_tx_999', $verified['tx_ref']);
        $this->assertEquals(2500.0, $verified['amount']);
    }

    public function test_is_valid_webhook_with_v4_hmac_sha256_base64_signature(): void
    {
        $service = new FlutterwaveService();
        $secretHash = 'test_secret_hash_789';
        $payload = json_encode([
            'id'        => 'wbk_123',
            'type'      => 'charge.completed',
            'timestamp' => 1735116884019,
            'data'      => [
                'id'        => 'chg_abc123',
                'status'    => 'succeeded',
                'amount'    => 2500,
                'currency'  => 'NGN',
                'reference' => 'tx_ref_001',
            ],
        ]);

        // Compute official Flutterwave v4 Base64 HMAC-SHA256 signature
        $validBase64Signature = base64_encode(hash_hmac('sha256', $payload, $secretHash, true));
        $this->assertTrue($service->isValidWebhook($validBase64Signature, $payload));

        // Direct secret match also accepted
        $this->assertTrue($service->isValidWebhook($secretHash, $payload));

        // Tampered payload rejected
        $tamperedPayload = json_encode(['data' => ['amount' => 100]]);
        $this->assertFalse($service->isValidWebhook($validBase64Signature, $tamperedPayload));

        // Invalid signature rejected
        $this->assertFalse($service->isValidWebhook('invalid_signature', $payload));
    }
}
