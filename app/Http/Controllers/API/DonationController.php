<?php

namespace App\Http\Controllers\API;

use App\Enums\Donations\Medium;
use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CampaignRepositoryInterface;
use App\Http\Repository\Contracts\DonationRepositoryInterface;
use App\Http\Requests\Campaign\DonationRequest;
use App\Http\Requests\Campaign\InitializeDonationRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\Donation\DonationResource;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Need;
use App\Models\Notification;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PaymentGateway;
use App\Services\PaystackService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DonationController extends Controller
{
    public function __construct(
        protected CampaignRepositoryInterface $campaignRepository,
        protected DonationRepositoryInterface $donationRepository,
        protected CurrencyService $currencyService,
        protected PaystackService $paystackService,
        protected PaymentGateway $paymentGateway
    ) {}

    public function index(Request $request)
    {
        $donatableType = match ($request->query('type')) {
            'campaign' => Campaign::class,
            'need', 'needs' => Need::class,
            default => null,
        };

        $donations = $this->donationRepository->index($donatableType);

        return $this->handleSuccessCollectionResponse('Donations fetched successfully', DonationResource::collection($donations));
    }

    /**
     * List available donation payment mediums.
     */
    public function mediums()
    {
        return $this->handleSuccessResponse('Donation mediums fetched successfully', Medium::options());
    }

    /**
     * Admin: view a single donation.
     */
    public function show(Donation $donation)
    {
        $donation->load(['donatable', 'user.profile', 'flaggedBy']);

        return $this->handleSuccessResponse('Donation fetched successfully', new DonationResource($donation));
    }

    /**
     * Admin: flag a donation as suspicious.
     */
    public function flag(Request $request, Donation $donation)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $donation->update([
            'flagged_at' => now(),
            'flag_reason' => $validated['reason'],
            'flagged_by' => auth()->id(),
        ]);

        $donation->load(['donatable', 'user.profile', 'flaggedBy']);

        return $this->handleSuccessResponse('Donation flagged successfully', new DonationResource($donation));
    }

    /**
     * Admin: remove the flag from a donation.
     */
    public function unflag(Donation $donation)
    {
        $donation->update([
            'flagged_at' => null,
            'flag_reason' => null,
            'flagged_by' => null,
        ]);

        $donation->load(['donatable', 'user.profile', 'flaggedBy']);

        return $this->handleSuccessResponse('Donation flag removed', new DonationResource($donation));
    }

    /**
     * Get top donor leaderboard ranked by total USD amount donated.
     */
    public function leaderboard(Request $request)
    {
        $limit = (int) ($request->limit ?? 10);
        $donatableType = null;
        if ($request->type === 'campaign') {
            $donatableType = Campaign::class;
        } elseif ($request->type === 'needs') {
            $donatableType = Need::class;
        }

        $donors = $this->donationRepository->leaderboard($limit, $donatableType, $request->id);

        return $this->handleSuccessResponse('Donor leaderboard fetched successfully', $donors);
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

        $baseAmountUsd = strtoupper($donorCurrency) === 'USD'
            ? (float) $amount
            : round($this->currencyService->convert((float) $amount, $donorCurrency, 'USD'), 2);

        try {
            return DB::transaction(function () use ($donatable, $type, $title, $user, $amount, $donorCurrency, $convertedAmount, $baseAmountUsd, $rate) {
                $user->withdraw($amount, "Donation to {$type}: {$title}");

                $donation = $this->donationRepository->create([
                    'donatable_id' => $donatable->id,
                    'donatable_type' => get_class($donatable),
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'currency' => $donorCurrency,
                    'converted_amount' => $convertedAmount,
                    'base_amount_usd' => $baseAmountUsd,
                    'rate' => $rate,
                    'medium' => Medium::WALLET,
                    'name' => $user->profile->first_name.' '.$user->profile->last_name,
                    'email' => $user->email,
                    'status' => 'completed',
                    'reference' => 'WAL_'.uniqid(),
                ]);

                // Notify donor
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Donation Successful',
                    'message' => "Your donation of {$donorCurrency} ".number_format($amount, 2)." to '{$title}' was successful.",
                    'type' => "{$type}_donation",
                    'data' => [
                        'donation_id' => $donation->id,
                        'donatable_id' => $donatable->id,
                        'donatable_type' => get_class($donatable),
                        'amount' => $amount,
                        'currency' => $donorCurrency,
                    ],
                ]);

                return $this->handleSuccessResponse('Donation successful', [
                    'donation' => $donation,
                ]);
            });
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    /**
     * Initialize a donation payment for the requested gateway.
     */
    public function initializePayment(InitializeDonationRequest $request, $type, $id)
    {
        try {
            $donatable = $this->getDonatable($type, $id);
            $gateway = strtolower((string) ($request->gateway ?? 'paystack'));
            if ($gateway === 'rave') {
                $gateway = Medium::FLUTTERWAVE->value;
            }

            $email = auth()->user()?->email ?? $request->email;
            $user = auth()->user() ?? User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower((string) $email)])
                ->first();

            $targetCurrency = $this->getDonatableCurrency($donatable, $type);
            $donorCurrency = strtoupper((string) ($user?->wallet?->currency ?? $request->currency ?? $targetCurrency));
            $name = $this->resolveDonorName($request->name, $user, $email);

            $amount = (float) $request->amount;
            $rate = $this->currencyService->getExchangeRate($donorCurrency, $targetCurrency);
            $convertedAmount = round($amount * $rate, 2);

            $baseAmountUsd = $donorCurrency === 'USD'
                ? $amount
                : round($this->currencyService->convert($amount, $donorCurrency, 'USD'), 2);

            $referencePrefix = match ($gateway) {
                Medium::STRIPE->value => 'STR_',
                Medium::PAYPAL->value => 'PPL_',
                Medium::FLUTTERWAVE->value => 'FLW_',
                Medium::NOMBA->value => 'NMB_',
                default => 'PAY_',
            };
            $reference = $referencePrefix.uniqid();

            $donation = $this->donationRepository->create([
                'donatable_id' => $donatable->id,
                'donatable_type' => get_class($donatable),
                'user_id' => $user?->id,
                'amount' => $amount,
                'currency' => $donorCurrency,
                'medium' => Medium::from($gateway),
                'name' => $name,
                'email' => $email,
                'converted_amount' => $convertedAmount,
                'base_amount_usd' => $baseAmountUsd,
                'rate' => $rate,
                'status' => 'pending',
                'reference' => $reference,
            ]);

            $result = $this->paymentGateway->initializeDonation($gateway, [
                'amount' => $amount,
                'currency' => $donorCurrency,
                'email' => $email,
                'name' => $name,
                'reference' => $reference,
                'user_id' => $user?->id,
                'donatable_id' => $donatable->id,
                'title' => 'Donation to '.$this->getDonatableTitle($donatable, $type),
                'callback_url' => config('app.url').'/donations/verify',
                'cancel_url' => config('app.url')."/{$type}/{$id}",
            ]);

            if (! empty($result['reference']) && $result['reference'] !== $reference) {
                $donation->update(['reference' => $result['reference']]);
            }

            return $this->handleSuccessResponse('Transaction initialized', $result);
        } catch (Throwable $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    protected function resolveDonorName(?string $requestedName, ?User $user, string $email): string
    {
        if (filled($requestedName)) {
            return $requestedName;
        }

        $profileName = trim(($user?->profile?->first_name ?? '').' '.($user?->profile?->last_name ?? ''));

        if ($profileName !== '') {
            return $profileName;
        }

        return $email;
    }

    /**
     * Verify Paystack donation
     */
    public function verifyPaystack(Request $request)
    {
        $reference = $request->reference;
        if (! $reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        try {
            $data = $this->paystackService->verifyTransaction($reference);

            if ($data['status'] === 'success') {
                $donation = $this->donationRepository->findByReference($reference);

                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'completed']);

                    if ($donation->user_id) {
                        $title = $donation->donatable_type === Campaign::class
                            ? $donation->donatable->title
                            : $donation->donatable->name;

                        Notification::create([
                            'user_id' => $donation->user_id,
                            'title' => 'Donation Successful',
                            'message' => "Your donation of {$donation->currency} ".number_format($donation->amount, 2)." to '{$title}' was successful.",
                            'type' => 'donation_success',
                            'data' => [
                                'donation_id' => $donation->id,
                                'donatable_id' => $donation->donatable_id,
                                'donatable_type' => $donation->donatable_type,
                            ],
                        ]);
                    }

                    $response = [
                        'donation' => $donation,
                    ];

                    if ($donation->donatable_type === Campaign::class) {
                        $response['campaign'] = new CampaignResource($donation->donatable->fresh());
                    } else {
                        $response['need'] = $donation->donatable->fresh();
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
