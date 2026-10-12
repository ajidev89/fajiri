<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\PlanActivationTrait;
use App\Jobs\Paystack\PaystackJob;
use App\Mail\DepositSuccessMail;
use App\Mail\SubscriptionSuccessMail;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\User;
use App\Services\FlutterwaveService;
use App\Services\NombaService;
use App\Services\PayPalService;
use App\Services\PaystackService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    use PlanActivationTrait;

    public function __construct(
        protected StripeService $stripeService,
        protected PaystackService $paystackService,
        protected PayPalService $payPalService,
        protected FlutterwaveService $flutterwaveService,
        protected NombaService $nombaService
    ) {}

    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $event = json_decode($payload, true);
        $type = $event['type'] ?? '';

        Log::info('Stripe Webhook Received', ['type' => $type]);

        switch ($type) {
            case 'checkout.session.completed':
                $this->handleStripeCheckoutCompleted($event['data']['object']);
                break;
            case 'customer.subscription.deleted':
                $this->handleStripeSubscriptionCancelled($event['data']['object']);
                break;
        }

        return response()->json(['message' => 'Webhook handled']);
    }

    public function handlePaystack(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $this->paystackService->isValidWebhook($signature, $payload)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        PaystackJob::dispatchAfterResponse($event);

        return response()->json(['message' => 'Webhook received and processing']);
    }

    public function handlePayPal(Request $request)
    {
        $payload = $request->getContent();
        $headers = $request->headers->all();

        if (! $this->payPalService->isValidWebhook($headers, $payload)) {
            Log::warning('PayPal Webhook invalid signature');
        }

        $event = json_decode($payload, true);
        $eventType = $event['event_type'] ?? '';

        Log::info('PayPal Webhook Received', ['event_type' => $eventType]);

        if (in_array($eventType, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'])) {
            $resource = $event['resource'] ?? [];
            $customId = $resource['purchase_units'][0]['custom_id'] ?? $resource['custom_id'] ?? null;
            $amount = $resource['purchase_units'][0]['amount']['value'] ?? $resource['amount']['value'] ?? 0;
            $currency = $resource['purchase_units'][0]['amount']['currency_code'] ?? $resource['amount']['currency_code'] ?? 'USD';

            if ($customId) {
                $user = User::find($customId);
                if ($user) {
                    $reference = $resource['id'] ?? 'paypal_'.time();
                    $user->deposit($amount, 'Wallet funding via PayPal', $reference);

                    Notification::create([
                        'user_id' => $user->id,
                        'title' => 'Wallet Funded',
                        'message' => "Your wallet has been credited with {$currency} ".number_format($amount, 2).' via PayPal.',
                        'type' => 'wallet_funding',
                        'data' => [
                            'amount' => $amount,
                            'reference' => $reference,
                            'currency' => $currency,
                        ],
                    ]);
                }
            }
        }

        return response()->json(['message' => 'PayPal webhook processed']);
    }

    public function handleFlutterwave(Request $request)
    {
        $signature = $request->header('verif-hash')
            ?? $request->header('verif_hash')
            ?? $request->header('flutterwave-signature')
            ?? $request->header('x-flutterwave-signature');
        $payload = $request->getContent();

        if (! $this->flutterwaveService->isValidWebhook($signature, $payload)) {
            Log::warning('Flutterwave webhook signature verification failed');

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->all();
        $eventType = strtolower($event['type'] ?? $event['event'] ?? '');
        $data = $event['data'] ?? $event;
        $eventStatus = strtolower($data['status'] ?? $event['status'] ?? '');
        $txRef = $data['reference'] ?? $data['tx_ref'] ?? $event['tx_ref'] ?? null;

        Log::info('Flutterwave Webhook Received', [
            'type' => $eventType,
            'status' => $eventStatus,
            'tx_ref' => $txRef,
        ]);

        $isSuccessEvent = $eventType === 'charge.completed'
            || in_array($eventStatus, ['succeeded', 'successful', 'completed', 'approved']);

        if ($isSuccessEvent) {
            $transactionId = $data['id'] ?? $event['id'] ?? null;
            if ($transactionId) {
                try {
                    $txData = $this->flutterwaveService->verifyTransaction((string) $transactionId);
                    if ($this->flutterwaveService->isSuccessful($txData)) {
                        $email = $txData['customer']['email'] ?? ($data['customer']['email'] ?? null);
                        $amount = (float) ($txData['amount'] ?? $data['amount'] ?? 0);
                        $currency = strtoupper($txData['currency'] ?? $data['currency'] ?? 'NGN');
                        $reference = $txData['reference'] ?? $txData['tx_ref'] ?? $txRef ?? (string) $transactionId;

                        if ($email && $amount > 0) {
                            $user = User::where('email', $email)->first();
                            if ($user) {
                                $user->deposit($amount, 'Wallet funding via Flutterwave', $reference);

                                Notification::create([
                                    'user_id' => $user->id,
                                    'title' => 'Wallet Funded',
                                    'message' => "Your wallet has been credited with {$currency} ".number_format($amount, 2).' via Flutterwave.',
                                    'type' => 'wallet_funding',
                                    'data' => [
                                        'amount' => $amount,
                                        'reference' => $reference,
                                        'currency' => $currency,
                                    ],
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Flutterwave webhook verification error: '.$e->getMessage());
                }
            }
        }

        return response()->json(['message' => 'Flutterwave webhook processed']);
    }

    public function handleNomba(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('x-nomba-signature');

        if (! $this->nombaService->isValidWebhook($signature, $payload)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $status = $event['data']['status'] ?? $event['status'] ?? '';

        Log::info('Nomba Webhook Received', ['status' => $status]);

        if (in_array(strtoupper($status), ['SUCCESSFUL', 'COMPLETED', 'SUCCESS'])) {
            $data = $event['data'] ?? [];
            $metadata = $this->nombaMetadata($data, $event);
            $reference = $data['orderReference'] ?? $event['orderReference'] ?? null;
            $type = $metadata['type'] ?? $this->nombaReferenceType($reference);

            if ($type === 'subscription') {
                $this->activateNombaSubscription($data, $metadata, $reference);
            } else {
                $this->creditNombaWallet($data, $metadata, $reference);
            }
        }

        return response()->json(['message' => 'Nomba webhook processed']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function nombaMetadata(array $data, array $event): array
    {
        $metadata = $data['orderMetaData']
            ?? $data['orderMetadata']
            ?? $data['metadata']
            ?? $event['orderMetaData']
            ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    protected function nombaReferenceType(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        if (str_starts_with($reference, 'nsub_')) {
            return 'subscription';
        }

        if (str_starts_with($reference, 'nwf_')) {
            return 'wallet_funding';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    protected function activateNombaSubscription(array $data, array $metadata, ?string $reference): void
    {
        $user = $this->nombaUser($data, $metadata);
        $plan = Plan::find($metadata['plan_id'] ?? null);

        if (! $user || ! $plan || ! $reference) {
            Log::error('Nomba subscription webhook could not be matched', [
                'reference' => $reference,
                'metadata' => $metadata,
            ]);

            return;
        }

        $alreadyActivated = DB::table('user_plans')
            ->where('provider', 'nomba')
            ->where('provider_subscription_id', $reference)
            ->exists();

        if ($alreadyActivated) {
            return;
        }

        $this->activateUserPlan($user, $plan, 'nomba', $reference);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    protected function creditNombaWallet(array $data, array $metadata, ?string $reference): void
    {
        $user = $this->nombaUser($data, $metadata);

        if (! $user || ! $reference) {
            return;
        }

        if ($user->transactions()->where('reference', $reference)->exists()) {
            return;
        }

        $amount = $data['amount'] ?? 0;
        $currency = strtoupper($data['currency'] ?? 'NGN');

        $user->deposit($amount, 'Wallet funding via Nomba', $reference);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Wallet Funded',
            'message' => "Your wallet has been credited with {$currency} ".number_format($amount, 2).' via Nomba.',
            'type' => 'wallet_funding',
            'data' => [
                'amount' => $amount,
                'reference' => $reference,
                'currency' => $currency,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    protected function nombaUser(array $data, array $metadata): ?User
    {
        if (! empty($metadata['user_id'])) {
            $user = User::find($metadata['user_id']);

            if ($user) {
                return $user;
            }
        }

        $email = $data['customerEmail'] ?? null;

        return $email ? User::where('email', $email)->first() : null;
    }

    protected function handleStripeCheckoutCompleted($session)
    {
        $userId = $session['metadata']['user_id'] ?? null;
        $type = $session['metadata']['type'] ?? null;

        if (! $userId) {
            return;
        }

        $user = User::find($userId);
        if (! $user) {
            return;
        }

        if ($type === 'wallet_funding') {
            $amount = $session['metadata']['amount'] ?? ($session['amount_total'] / 100);
            $reference = $session['id'];
            $currency = strtoupper($session['metadata']['currency'] ?? $session['currency']);

            $user->deposit($amount, 'Wallet funding via Stripe', $reference);

            Notification::create([
                'user_id' => $user->id,
                'title' => 'Wallet Funded',
                'message' => "Your wallet has been credited with {$currency} ".number_format($amount, 2).' via Stripe.',
                'type' => 'wallet_funding',
                'data' => [
                    'amount' => $amount,
                    'reference' => $reference,
                    'currency' => $currency,
                ],
            ]);

            try {
                Mail::to($user->email)->send(new DepositSuccessMail($user, $amount, $currency, $reference));
            } catch (\Exception $e) {
                \Log::error('Failed to send stripe deposit email: '.$e->getMessage());
            }
        } else {
            $planId = $session['metadata']['plan_id'] ?? null;
            $subscriptionId = $session['subscription'] ?? null;

            if ($planId) {
                $plan = Plan::find($planId);
                if ($plan) {
                    $this->activateUserPlan($user, $plan, 'stripe', $subscriptionId);

                    try {
                        Mail::to($user->email)->send(new SubscriptionSuccessMail($user, $plan, $plan->price, $plan->currency));
                    } catch (\Exception $e) {
                        \Log::error('Failed to send stripe subscription email: '.$e->getMessage());
                    }
                }
            }
        }
    }

    protected function handleStripeSubscriptionCancelled($subscription)
    {
        $subscriptionId = $subscription['id'];
        $this->deactivateUserPlanBySubscriptionId('stripe', $subscriptionId);
    }
}
