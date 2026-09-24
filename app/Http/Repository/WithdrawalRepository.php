<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\WithdrawalRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Notification;
use App\Models\WithdrawalAccount;
use App\Services\PaystackService;
use App\Services\StripeService;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalRepository implements WithdrawalRepositoryInterface
{
    use AuthUserTrait, ResponseTrait;

    public function __construct(public WithdrawalAccount $account, protected PaystackService $paystackService, protected StripeService $stripeService) {}

    public function store($request)
    {
        if ($this->user()->withdrawalAccounts()->count() > 0) {
            return $this->handleErrorResponse('User already has a withdrawal account');
        }

        if (! Hash::check($request->pin, $this->user()->pin)) {
            return $this->handleErrorResponse('Invalid transaction PIN');
        }

        $medium = $this->medium();
        $recipient = null;

        try {
            switch ($medium) {
                case 'paystack':
                    $recipientData = $this->paystackService->createRecipent([
                        'type' => 'nuban',
                        'name' => $request->account_name,
                        'account_number' => $request->account_number,
                        'bank_code' => $request->bank_code,
                        'currency' => $this->user()->country->currency ?? 'NGN',
                    ]);
                    $recipientId = $recipientData['recipient_code'];
                    break;

                case 'stripe':
                    if (!$this->user()->stripe_connected_id) {
                        $stripeAccount = $this->stripeService->createConnectedAccount($this->user());
                        $this->user()->update(['stripe_connected_id' => $stripeAccount->id]);
                    }

                    $recipientData = $this->stripeService->bankAccount($this->user(), [
                        'account_name' => $request->account_name,
                        'account_number' => $request->account_number,
                        'routing_number' => $request->bank_code, // Using bank_code as routing_number for Stripe
                    ]);
                    $recipientId = $recipientData->id;
                    break;

                default:
                    return $this->handleErrorResponse('Unsupported payment medium');
            }

            $account = $this->account->create([
                'bank_name' => $request->bank_name,
                'account_name' => $request->account_name,
                'meta' => [
                    'provider' => $medium,
                    'provider_id' => $recipientId,
                ],
                'account_number' => $request->account_number,
                'routing_number' => $request->bank_code,
                'user_id' => $this->user()->id,
                'default' => true,
            ]);

            return $this->handleSuccessResponse('Successfully created withdrawal account', $account);

        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function index()
    {
        $accounts = $this->user()->withdrawalAccounts()->paginate();

        return $this->handleSuccessResponse('Successfully fetched accounts', $accounts);
    }

    public function destroy($request)
    {
        $account = $this->user()->withdrawalAccounts()->find($request->id);
        if (! $account) {
            return $this->handleErrorResponse('Account not found');
        }
        $account->delete();

        return $this->handleSuccessResponse('Successfully deleted withdrawal account', []);
    }

    public function banks()
    {
        $banks = $this->paystackService->banks();

        return $this->handleSuccessResponse('Successfully fetched banks', $banks);
    }

    public function resolveBankAccount($request)
    {
        try {
            $country = strtoupper((string) ($this->user()->country?->iso2 ?? ''));

            if (in_array($country, ['US', 'CA'], true)) {
                return $this->resolveStripeBankAccount($request, $country);
            }

            $account = $this->paystackService->resolveBankAccount([
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code,
            ]);

            return $this->handleSuccessResponse('Successfully resolved account', $account);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    protected function resolveStripeBankAccount(Request $request, string $country): JsonResponse
    {
        if ($country === 'US') {
            $customerId = $this->user()->stripe_customer_id;

            if (! $customerId) {
                $customerId = $this->stripeService->createCustomer($this->user());
                $this->user()->update(['stripe_customer_id' => $customerId]);
            }

            if ($request->filled('session_id')) {
                $account = $this->stripeService->resolveFinancialConnectionsSession((string) $request->string('session_id'), $customerId);

                return $this->handleSuccessResponse('Successfully resolved account', $account);
            }

            $session = $this->stripeService->createFinancialConnectionsSession($customerId);

            return $this->handleSuccessResponse('Continue in Stripe to link the bank account', $session);
        }

        if (! $request->filled('account_number') || ! $request->filled('bank_code')) {
            return $this->handleErrorResponse('Account number and routing number are required');
        }

        $account = $this->stripeService->verifyCanadianBankAccount(
            (string) $request->string('account_number'),
            (string) $request->string('bank_code'),
        );

        return $this->handleSuccessResponse('Successfully verified bank account', $account);
    }

    public function withdraw($request)
    {
        $user = $this->user();

        if (! Hash::check($request->pin, $user->pin)) {
            return $this->handleErrorResponse('Invalid transaction PIN');
        }

        $account = $user->withdrawalAccounts()->where('default', true)->first();
        if (! $account) {
            return $this->handleErrorResponse('No default withdrawal account found. Please add one.');
        }

        // Wallet check happens inside $user->withdraw(), but we can do a preliminary check
        if ($user->wallet->balance < $request->amount) {
            return $this->handleErrorResponse('Insufficient wallet balance');
        }

        return DB::transaction(function () use ($user, $account, $request) {
            try {
                $medium = $this->medium();
                $providerId = $account->meta['provider_id'];

                if ($medium === 'paystack') {
                    $this->paystackService->transfer([
                        'source' => 'balance',
                        'amount' => $request->amount * 100, // Paystack uses kobo
                        'recipient' => $providerId,
                        'reason' => 'Wallet Withdrawal',
                    ]);
                } else {
                    $this->stripeService->transfer([
                        'amount' => $request->amount * 100,
                        'destination' => $providerId,
                        'currency' => strtolower($user->country->currency ?? 'usd'),
                    ]);
                }

                // Log the transaction and deduct balance
                $transaction = $user->withdraw($request->amount, "Withdrawal to {$account->account_name}");

                // Add notification
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Withdrawal Initiated',
                    'message' => 'Your withdrawal of '.($user->wallet->currency ?? 'NGN').' '.number_format($request->amount, 2).' has been initiated.',
                    'type' => 'withdrawal_initiated',
                    'data' => [
                        'amount' => $request->amount,
                        'account_name' => $account->account_name,
                        'account_number' => $account->account_number,
                    ],
                ]);

                return $this->handleSuccessResponse('Withdrawal initiated successfully', $transaction);
            } catch (\Exception $e) {
                return $this->handleErrorResponse($e->getMessage());
            }
        });
    }

    protected function medium(): string
    {
        return ($this->user()->canUsePaystack()) ? 'paystack' : 'stripe';
    }
}
