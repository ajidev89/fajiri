<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Services\PaystackService;
use App\Models\User;
use App\Services\StripeService;
use App\Models\Transaction;
use App\Models\Donation;
use Exception;

class PaymentRepository implements PaymentRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(
        protected PaystackService $paystackService,
        protected StripeService $stripeService
    ) {}

    public function initialize($user, array $data)
    {
        try {
            $currency = $user->country->currency ?? 'NGN';
            $amount = $data['amount'];

            if ($currency === 'NGN') {
                $payload = [
                    'amount' => $amount * 100, // Convert to kobo
                    'email' => $data['email'],
                    'callback_url' => $data['callback_url'] ?? "https://app.fajiri.org/payment/callback",
                    'metadata' => [
                        'user_id' => $user->id,
                        'type' => 'wallet_funding'
                    ]
                ];

                $result = $this->paystackService->initializeTransaction($payload);
                return $this->handleSuccessResponse('Transaction initialized', $result);
            } else {
                // Use Stripe for non-NGN currencies (like CAD)
                $successUrl = $data['callback_url'] ?? "https://app.fajiri.org/payment/callback?status=success";
                $cancelUrl = $data['cancel_url'] ?? "https://app.fajiri.org/payment/callback?status=cancel";

                $session = $this->stripeService->createOneTimePaymentSession(
                    $user,
                    $amount,
                    $currency,
                    $successUrl,
                    $cancelUrl,
                    'Wallet Funding',
                    'Funding your Fajiri wallet'
                );

                return $this->handleSuccessResponse('Checkout session created', [
                    'authorization_url' => $session->url,
                    'access_code' => $session->id,
                    'reference' => $session->id
                ]);
            }
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

                //add notification  
                $user->notifications()->create([
                    'type' => 'wallet_funded',
                    'message' => "Wallet funded successfully",
                    'data' => [
                        'amount' => $amount,
                        'reference' => $reference
                    ]
                ]);

                // Send Email
                try {
                    \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\DepositSuccessMail($user, $amount, $user->wallet->currency, $reference));
                } catch (\Exception $e) {
                    \Log::error('Failed to send deposit email: ' . $e->getMessage());
                }

                return $this->handleSuccessResponse('Wallet funded successfully', $user->wallet);
            }

            return $this->handleErrorResponse('Payment verification failed');

        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

}
