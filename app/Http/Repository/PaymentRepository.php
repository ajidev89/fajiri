<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\Services\PayPalService;
use App\Services\FlutterwaveService;
use App\Services\NombaService;
use App\Models\User;
use Exception;

class PaymentRepository implements PaymentRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(
        protected PaystackService $paystackService,
        protected StripeService $stripeService,
        protected PayPalService $payPalService,
        protected FlutterwaveService $flutterwaveService,
        protected NombaService $nombaService
    ) {}

    public function initialize($user, array $data)
    {
        try {
            $currency = strtoupper($user->country->currency ?? $data['currency'] ?? 'NGN');
            $amount   = (float) $data['amount'];
            $provider = strtolower($data['provider'] ?? ($currency === 'NGN' ? 'paystack' : 'stripe'));

            switch ($provider) {
                case 'paypal':
                    $order = $this->payPalService->createOrder([
                        'amount'      => $amount,
                        'currency'    => $currency === 'NGN' ? 'USD' : $currency,
                        'description' => 'Wallet Funding for ' . $user->email,
                        'reference'   => (string) $user->id,
                        'return_url'  => $data['callback_url'] ?? config('app.url') . '/payments/verify/paypal',
                        'cancel_url'  => $data['cancel_url'] ?? config('app.url') . '/plans',
                    ]);

                    $approveUrl = collect($order['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

                    return $this->handleSuccessResponse('PayPal order created', [
                        'authorization_url' => $approveUrl,
                        'order_id'          => $order['id'] ?? null,
                        'raw'               => $order,
                    ]);

                case 'flutterwave':
                case 'rave':
                    $result = $this->flutterwaveService->initializeTransaction([
                        'amount'       => $amount,
                        'currency'     => $currency,
                        'email'        => $data['email'] ?? $user->email,
                        'name'         => $user->name ?? $user->profile?->first_name,
                        'redirect_url' => $data['callback_url'] ?? config('app.url') . '/payments/verify/flutterwave',
                        'title'        => 'Wallet Funding',
                        'description'  => 'Funding Fajiri wallet',
                        'meta'         => [
                            'user_id' => $user->id,
                            'type'    => 'wallet_funding',
                        ],
                    ]);

                    return $this->handleSuccessResponse('Flutterwave transaction initialized', [
                        'authorization_url' => $result['link'] ?? null,
                        'reference'         => $result['tx_ref'] ?? null,
                    ]);

                case 'nomba':
                    $result = $this->nombaService->createCheckoutOrder([
                        'amount'       => $amount,
                        'currency'     => $currency,
                        'email'        => $data['email'] ?? $user->email,
                        'name'         => $user->name ?? $user->profile?->first_name,
                        'callback_url' => $data['callback_url'] ?? config('app.url') . '/payments/verify/nomba',
                        'description'  => 'Wallet Funding',
                        'reference'    => 'nomba_wf_' . $user->id . '_' . time(),
                    ]);

                    return $this->handleSuccessResponse('Nomba checkout created', [
                        'authorization_url' => $result['checkoutUrl'] ?? $result['link'] ?? null,
                        'order_reference'   => $result['orderReference'] ?? null,
                    ]);

                case 'stripe':
                    $successUrl = $data['callback_url'] ?? config('app.url') . '/payments/verify/stripe?status=success';
                    $cancelUrl  = $data['cancel_url'] ?? config('app.url') . '/plans';

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
                        'access_code'       => $session->id,
                        'reference'         => $session->id,
                    ]);

                case 'paystack':
                default:
                    $payload = [
                        'amount'       => $amount * 100,
                        'email'        => $data['email'] ?? $user->email,
                        'callback_url' => $data['callback_url'] ?? config('app.url') . '/payments/verify/paystack',
                        'metadata'     => [
                            'user_id' => $user->id,
                            'type'    => 'wallet_funding',
                        ],
                    ];

                    $result = $this->paystackService->initializeTransaction($payload);
                    return $this->handleSuccessResponse('Paystack transaction initialized', $result);
            }
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function verify(string $reference)
    {
        try {
            $provider = request()->get('provider', 'paystack');

            if ($provider === 'paypal') {
                $capture = $this->payPalService->captureOrder($reference);
                if (($capture['status'] ?? '') === 'COMPLETED') {
                    $amount = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
                    $user = $this->user();
                    $user->deposit($amount, "Wallet funding via PayPal", $reference);
                    return $this->handleSuccessResponse('Wallet funded via PayPal successfully', $user->wallet);
                }
                return $this->handleErrorResponse('PayPal verification failed');
            }

            if ($provider === 'flutterwave') {
                $tx = $this->flutterwaveService->verifyTransaction($reference);
                if ($this->flutterwaveService->isSuccessful($tx)) {
                    $amount = (float) ($tx['amount'] ?? 0);
                    $user = $this->user();
                    $user->deposit($amount, "Wallet funding via Flutterwave", $reference);
                    return $this->handleSuccessResponse('Wallet funded via Flutterwave successfully', $user->wallet);
                }
                return $this->handleErrorResponse('Flutterwave verification failed');
            }

            if ($provider === 'nomba') {
                $order = $this->nombaService->getOrderStatus($reference);
                if (in_array(strtoupper($order['status'] ?? ''), ['SUCCESSFUL', 'COMPLETED', 'SUCCESS'])) {
                    $amount = $order['amount'];
                    $user = $this->user();
                    $user->deposit($amount, "Wallet funding via Nomba", $reference);
                    return $this->handleSuccessResponse('Wallet funded via Nomba successfully', $user->wallet);
                }
                return $this->handleErrorResponse('Nomba verification failed');
            }

            // Fallback to Paystack
            $data = $this->paystackService->verifyTransaction($reference);

            if ($data['status'] === 'success') {
                $userId = $data['metadata']['user_id'];
                $amount = $data['amount'] / 100;

                $user = User::find($userId);
                $user->deposit($amount, "Wallet funding via Paystack", $reference);

                $user->notifications()->create([
                    'type'    => 'wallet_funded',
                    'message' => "Wallet funded successfully",
                    'data'    => [
                        'amount'    => $amount,
                        'reference' => $reference,
                    ],
                ]);

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
