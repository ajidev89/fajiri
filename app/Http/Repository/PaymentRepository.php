<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Services\PaystackService;
use App\Models\User;
use App\Models\Transaction;
use Exception;

class PaymentRepository implements PaymentRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(protected PaystackService $paystackService)
    {}

    public function initialize($user, array $data)
    {
        try {
            $payload = [
                'amount' => $data['amount'] * 100, // Convert to kobo
                'email' => $data['email'],
                'callback_url' => $data['callback_url'] ?? config('app.url') . '/payments/verify',
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'wallet_funding'
                ]
            ];

            $result = $this->paystackService->initializeTransaction($payload);

            return $this->handleSuccessResponse('Transaction initialized', $result);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function verify(string $reference)
    {
        try {
            $data = $this->paystackService->verifyTransaction($reference);

            if ($data['status'] === 'success') {
                $userId = $data['metadata']['user_id'];
                $amount = $data['amount'] / 100;

                $user = User::find($userId);
                
                // Credit wallet using the HasWallet trait
                $user->deposit($amount, "Wallet funding via Paystack", $reference);

                return $this->handleSuccessResponse('Wallet funded successfully', $user->wallet);
            }

            return $this->handleErrorResponse('Payment verification failed');

        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function handleWebhook(array $event, string $signature, string $payload)
    {
        if (!$this->paystackService->isValidWebhook($signature, $payload)) {
            return response()->json(['status' => 'error'], 400);
        }

        if ($event['event'] === 'charge.success') {
            $data = $event['data'];
            $reference = $data['reference'];
            
            // Check if already processed to avoid double crediting
            $transactionExists = Transaction::where('reference', $reference)->exists();

            if (!$transactionExists) {
                $userId = $data['metadata']['user_id'];
                $amount = $data['amount'] / 100;

                $user = User::find($userId);
                $user->deposit($amount, "Wallet funding via Paystack (Webhook)", $reference);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }
}
