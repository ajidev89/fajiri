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
        $response = Http::withToken($this->secretKey)->get('/bank',$query);
        return $response->json();
    }

    public function resolveBankAccount($query = []){
        $response = Http::withToken($this->secretKey)->get('/bank/resolve',$query);
        return $response->json('data');
    }

    public function createRecipent($data){
        $response = Http::withToken($this->secretKey)->post('/transferrecipient',$data);
        return $response->json('data');
    }   

    public function transfer($data){
        $response = Http::withToken($this->secretKey)->post('/transfer',$data);
        return $response->json('data');
    }   
    
    /**
     * Handle Webhook Signature Verification
     */
    public function isValidWebhook(string $signature, string $payload): bool
    {
        return $signature === hash_hmac('sha512', $payload, $this->secretKey);
    }
}
