<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Exception;

class PaystackService
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = Config::get('paystack.paymentUrl');
        $this->secretKey = Config::get('paystack.secretKey');
    }

    /**
     * Initialize a transaction
     */
    public function initializeTransaction(array $data)
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $data);

        if ($response->failed()) {
            throw new Exception("Paystack initialization failed: " . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Verify a transaction
     */
    public function verifyTransaction(string $reference)
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if ($response->failed()) {
            throw new Exception("Paystack verification failed: " . $response->body());
        }

        return $response->json('data');
    }

    public function banks($query = [] ){
        $response = Http::withToken($this->secretKey)->get("{$this->baseUrl}/bank",$query);
        return $response->json('data');
    }

    public function resolveBankAccount($query = []){
        $response = Http::withToken($this->secretKey)->get("{$this->baseUrl}/bank/resolve",$query);
        return $response->json('data');
    }

    public function createRecipent($data){
        $response = Http::withToken($this->secretKey)->post("{$this->baseUrl}/transferrecipient",$data);
        return $response->json('data');
    }   

    public function transfer($data){
        $response = Http::withToken($this->secretKey)->post("{$this->baseUrl}/transfer",$data);
        return $response->json('data');
    }   
    
    /**
     * Create a Paystack Plan
     */
    public function createPlan(array $data)
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/plan", $data);

        if ($response->failed()) {
            throw new Exception("Paystack plan creation failed: " . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Initialize a Subscription (Initialize Transaction with Plan)
     */
    public function initializeSubscription(array $data)
    {
        // Paystack uses 'plan' parameter in transaction initialization to start a subscription
        return $this->initializeTransaction($data);
    }

    /**
     * Cancel Subscription
     */
    public function cancelSubscription($subscriptionCode, $emailToken)
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription/disable", [
                'code' => $subscriptionCode,
                'token' => $emailToken
            ]);

        return $response->json();
    }

    /**
     * Handle Webhook Signature Verification
     */
    public function isValidWebhook(string $signature, string $payload): bool
    {
        return $signature === hash_hmac('sha512', $payload, $this->secretKey);
    }
}
