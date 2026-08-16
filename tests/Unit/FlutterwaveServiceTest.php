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

        Config::set('flutterwave.publicKey', 'FLWPUBK_TEST-123456789-X');
        Config::set('flutterwave.secretKey', 'FLWSECK_TEST-987654321-X');
        Config::set('flutterwave.secretHash', 'test_secret_hash_789');
        Config::set('flutterwave.paymentUrl', 'https://api.flutterwave.com/v3');
    }

    public function test_initialize_transaction_sends_v3_payments_request(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status'  => 'success',
                'message' => 'Hosted Link',
                'data'    => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/flw_hosted_123',
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService();

        $result = $service->initializeTransaction([
            'amount'       => 5000.0,
            'currency'     => 'NGN',
            'email'        => 'john@example.com',
            'name'         => 'John Doe',
            'phone'        => '08099887766',
            'tx_ref'       => 'test_ref_001',
            'redirect_url' => 'https://fajiri.app/payments/verify/flutterwave',
        ]);

        $this->assertEquals('https://checkout.flutterwave.com/v3/hosted/pay/flw_hosted_123', $result['link']);
        $this->assertEquals('https://checkout.flutterwave.com/v3/hosted/pay/flw_hosted_123', $result['authorization_url']);
        $this->assertEquals('test_ref_001', $result['tx_ref']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.flutterwave.com/v3/payments'
                && $request->hasHeader('Authorization', 'Bearer FLWSECK_TEST-987654321-X')
                && $request['amount'] == 5000.0
                && $request['currency'] === 'NGN'
                && $request['tx_ref'] === 'test_ref_001'
                && $request['customer']['email'] === 'john@example.com'
                && $request['customer']['name'] === 'John Doe'
                && $request['customer']['phonenumber'] === '08099887766';
        });
    }

    public function test_verify_transaction_by_id_in_v3(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/2882001/verify' => Http::response([
                'status'  => 'success',
                'message' => 'Transaction fetched successfully',
                'data'    => [
                    'id'        => 2882001,
                    'tx_ref'    => 'ref_tx_999',
                    'flw_ref'   => 'FLW-MOCK-12345',
                    'amount'    => 5000,
                    'currency'  => 'NGN',
                    'status'    => 'successful',
                    'customer'  => [
                        'email' => 'user@example.com',
                        'name'  => 'John Doe',
                    ],
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService();
        $verified = $service->verifyTransaction('2882001');

        $this->assertTrue($verified['is_successful']);
        $this->assertTrue($service->isSuccessful($verified));
        $this->assertEquals('ref_tx_999', $verified['tx_ref']);
        $this->assertEquals('ref_tx_999', $verified['reference']);
        $this->assertEquals(5000, $verified['amount']);
    }

    public function test_verify_transaction_by_ref_in_v3(): void
    {
        Http::fake([
            'https://api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response([
                'status'  => 'success',
                'message' => 'Transaction fetched successfully',
                'data'    => [
                    'id'       => 2882002,
                    'tx_ref'   => 'ref_tx_ref_query',
                    'amount'   => 15000,
                    'currency' => 'NGN',
                    'status'   => 'successful',
                ],
            ], 200),
        ]);

        $service = new FlutterwaveService();
        $verified = $service->verifyTransactionByRef('ref_tx_ref_query');

        $this->assertTrue($verified['is_successful']);
        $this->assertEquals('ref_tx_ref_query', $verified['tx_ref']);
        $this->assertEquals(15000, $verified['amount']);
    }

    public function test_is_valid_webhook_verifies_v3_secret_hash(): void
    {
        $service = new FlutterwaveService();
        $secretHash = 'test_secret_hash_789';

        // Valid direct secret hash (v3 verif-hash standard)
        $this->assertTrue($service->isValidWebhook($secretHash));

        // Invalid secret hash rejected
        $this->assertFalse($service->isValidWebhook('invalid_hash'));
        $this->assertFalse($service->isValidWebhook(null));
    }
}

