<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Requests\Campaign\DonationRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\Donation\DonationResource;
use App\Models\Campaign;
use App\Models\Need;
use App\Services\CurrencyService;
use App\Services\PaystackService;
use App\Services\StripeService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Enums\Donations\Medium;

class DonationController extends Controller
{
    public function __construct(
        protected CampaignRepositoryInterface $campaignRepository,
        protected DonationRepositoryInterface $donationRepository,
        protected CurrencyService $currencyService,
        protected PaystackService $paystackService,
        protected StripeService $stripeService
    ) {}

    public function index()
    {
        $donations = $this->donationRepository->index();
        return $this->handleSuccessCollectionResponse('Donations fetched successfully', DonationResource::collection($donations));
    }

    protected function getDonatable($type, $id)
    {
        if ($type === 'campaign') {
            return Campaign::findOrFail($id);
        } elseif ($type === 'needs') {
            return Need::findOrFail($id);
        }
        abort(404, 'Invalid donation type');
    }

    protected function getDonatableTitle($donatable, $type)
    {
        return $type === 'campaign' ? $donatable->title : $donatable->name;
    }

    protected function getDonatableCurrency($donatable, $type)
    {
        return $type === 'campaign' ? ($donatable->currency ?? 'NGN') : 'NGN';
    }

    /**
     * Donate using wallet balance
     */
    public function donateViaWallet(DonationRequest $request, $type, $id)
    {
        $donatable = $this->getDonatable($type, $id);
        $title = $this->getDonatableTitle($donatable, $type);
        $user = auth()->user();
        $donorCurrency = $user->wallet->currency ?? 'NGN';
        $targetCurrency = $this->getDonatableCurrency($donatable, $type);

        $amount = $request->amount;
        $rate = $this->currencyService->getExchangeRate($donorCurrency, $targetCurrency);
        $convertedAmount = round($amount * $rate, 2);

        try {
            return DB::transaction(function () use ($donatable, $type, $title, $user, $amount, $donorCurrency, $convertedAmount, $rate) {
                $user->withdraw($amount, "Donation to {$type}: {$title}");

                $donation = $this->donationRepository->create([
                    'donatable_id' => $donatable->id,
                    'donatable_type' => get_class($donatable),
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $donorCurrency,
                    'converted_amount' => $convertedAmount,
                    'rate' => $rate,
                    'medium' => Medium::WALLET,
                    'name' => $user->profile->first_name . ' ' . $user->profile->last_name,
                    'email' => $user->email,
                    'status' => 'completed',
                    'reference' => 'WAL_' . uniqid(),
                ]);

                // Notify donor
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Donation Successful',
                    'message' => "Your donation of {$donorCurrency} " . number_format($amount, 2) . " to '{$title}' was successful.",
                    'type' => "{$type}_donation",
                    'data' => [
                        'donation_id' => $donation->id,
                        'donatable_id' => $donatable->id,
                        'donatable_type' => get_class($donatable),
                        'amount' => $amount,
                        'currency' => $donorCurrency
                    ]
                ]);

                return $this->handleSuccessResponse('Donation successful', [
                    'donation' => $donation
                ]);
            });
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Initialize donation payment
     */
    public function initializePayment(DonationRequest $request, $type, $id)
    {
        $donatable = $this->getDonatable($type, $id);
        $user = auth()->user();
        
        $targetCurrency = $this->getDonatableCurrency($donatable, $type);
        $donorCurrency = $user ? ($user->wallet->currency ?? 'NGN') : ($request->currency ?? $targetCurrency);
        $email = $user ? $user->email : $request->email;
        $name = $user ? $user->profile->first_name . ' ' . $user->profile->last_name : $request->name;

        $amount = $request->amount;
        $rate = $this->currencyService->getExchangeRate($donorCurrency, $targetCurrency);
        $convertedAmount = round($amount * $rate, 2);

        try {
            $reference = (strtoupper($donorCurrency) === 'NGN' ? 'PAY_' : 'STR_') . uniqid();
            
            // Create pending donation
            $this->donationRepository->create([
                'donatable_id' => $donatable->id,
                'donatable_type' => get_class($donatable),
                'user_id' => $user->id ?? null,
                'amount' => $amount,
                'currency' => $donorCurrency,
                'medium' => strtoupper($donorCurrency) === 'NGN' ? Medium::PAYSTACK : Medium::STRIPE,
                'name' => $name,
                'email' => $email,
                'converted_amount' => $convertedAmount,
                'rate' => $rate,
                'status' => 'pending',
                'reference' => $reference,
            ]);

            if (strtoupper($donorCurrency) === 'NGN') {
                $payload = [
                    'amount' => $amount * 100,
                    'email' => $email,
                    'reference' => $reference,
                    'callback_url' => config('app.url') . '/donations/verify',
                    'metadata' => [
                        'donatable_id' => $donatable->id,
                        'user_id' => $user->id ?? null,
                        'type' => "donation"
                    ]
                ];

                $result = $this->paystackService->initializeTransaction($payload);
            } else {
                // Use Stripe for non-NGN. 
                // IMPORTANT: We must convert the amount from the item's base currency (targetCurrency) 
                // to the donor's currency (donorCurrency) to ensure correct charging on Stripe.
                $stripeAmount = $this->currencyService->convert($amount, $targetCurrency, $donorCurrency);

                $session = $this->stripeService->createOneTimePaymentSession(
                    $user ?? (object)['email' => $email],
                    $stripeAmount,
                    $donorCurrency,
                    config('app.url') . '/donations/verify',
                    config('app.url') . "/{$type}/{$id}",
                    'Donation',
                    "Donation to " . $this->getDonatableTitle($donatable, $type),
                    [
                        'donatable_id' => $donatable->id,
                        'type' => 'donation',
                        'reference' => $reference,
                        'original_amount' => $amount,
                        'original_currency' => $targetCurrency
                    ]
                );

                $result = [
                    'authorization_url' => $session->url,
                    'reference' => $reference
                ];
            }

            return $this->handleSuccessResponse('Transaction initialized', $result);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Verify Paystack donation
     */
    public function verifyPaystack(Request $request)
    {
        $reference = $request->reference;
        if (!$reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        try {
            $data = $this->paystackService->verifyTransaction($reference);

            if ($data['status'] === 'success') {
                $donation = $this->donationRepository->findByReference($reference);
                
                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'completed']);
                    
                    $response = [
                        'donation' => $donation,
                    ];
                    
                    if ($donation->donatable_type === Campaign::class) {
                        $response['campaign'] = new CampaignResource($donation->donatable->fresh());
                    } else {
                        $response['need'] = $donation->donatable->fresh(); // No NeedResource yet
                    }

                    return $this->handleSuccessResponse('Donation verified successfully', $response);
                }
            }

            return $this->handleErrorResponse('Donation verification failed', 400);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}
