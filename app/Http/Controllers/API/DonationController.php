<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Requests\Campaign\DonationRequest;
use App\Http\Resources\CampaignResource;
use App\Services\CurrencyService;
use App\Services\PaystackService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonationController extends Controller
{
    public function __construct(
        protected CampaignRepositoryInterface $campaignRepository,
        protected DonationRepositoryInterface $donationRepository,
        protected CurrencyService $currencyService,
        protected PaystackService $paystackService
    ) {}

    /**
     * Donate using wallet balance
     */
    public function donateViaWallet(DonationRequest $request, $campaignId)
    {
        $campaign = $this->campaignRepository->find($campaignId);
        $user = auth()->user();
        $donorCurrency = $user->wallet->currency ?? 'NGN';
        $campaignCurrency = $campaign->currency ?? 'NGN';

        $amount = $request->amount;
        $rate = $this->currencyService->getExchangeRate($donorCurrency, $campaignCurrency);
        $convertedAmount = round($amount * $rate, 2);

        try {
            return DB::transaction(function () use ($campaign, $user, $amount, $donorCurrency, $convertedAmount, $rate) {
                $user->withdraw($amount, "Donation to campaign: {$campaign->title}");

                $donation = $this->donationRepository->create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $donorCurrency,
                    'converted_amount' => $convertedAmount,
                    'rate' => $rate,
                    'status' => 'completed',
                    'reference' => 'WAL_' . uniqid(),
                ]);

                // Notify donor
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Donation Successful',
                    'message' => "Your donation of {$donorCurrency} " . number_format($amount, 2) . " to '{$campaign->title}' was successful.",
                    'type' => 'campaign_donation',
                    'data' => [
                        'donation_id' => $donation->id,
                        'campaign_id' => $campaign->id,
                        'amount' => $amount,
                        'currency' => $donorCurrency
                    ]
                ]);


                return response()->json([
                    'message' => 'Donation successful',
                    'donation' => $donation,
                    'campaign' => new CampaignResource($campaign->fresh())
                ]);
            });
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Initialize Paystack donation
     */
    public function initializePaystack(DonationRequest $request, $campaignId)
    {
        $campaign = $this->campaignRepository->find($campaignId);
        $user = auth()->user();
        
        $donorCurrency = $user ? ($user->wallet->currency ?? 'NGN') : ($campaign->currency ?? 'NGN');
        $campaignCurrency = $campaign->currency ?? 'NGN';
        $email = $user ? $user->email : $request->email;

        $amount = $request->amount;
        $rate = $this->currencyService->getExchangeRate($donorCurrency, $campaignCurrency);
        $convertedAmount = round($amount * $rate, 2);

        try {
            $reference = 'PAY_' . uniqid();
            
            // Create pending donation
            $this->donationRepository->create([
                'campaign_id' => $campaign->id,
                'user_id' => $user->id ?? null,
                'amount' => $amount,
                'currency' => $donorCurrency,
                'converted_amount' => $convertedAmount,
                'rate' => $rate,
                'status' => 'pending',
                'reference' => $reference,
            ]);

            $payload = [
                'amount' => $amount * 100, // Paystack uses kobo/cents
                'email' => $email,
                'reference' => $reference,
                'callback_url' => config('app.url') . '/donations/verify',
                'metadata' => [
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id ?? null,
                    'type' => 'campaign_donation'
                ]
            ];

            $result = $this->paystackService->initializeTransaction($payload);

            return response()->json([
                'message' => 'Transaction initialized',
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
                    return response()->json([
                        'message' => 'Donation verified successfully',
                        'donation' => $donation,
                        'campaign' => new CampaignResource($donation->campaign->fresh())
                    ]);
                }
            }

            return response()->json(['message' => 'Donation verification failed'], 400);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
