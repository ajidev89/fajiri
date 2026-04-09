<?php
namespace App\Http\Repository;

use App\Http\Repository\Contracts\WithdrawalRepositoryInterface;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\WithdrawalAccount;
use Hash;
use Illuminate\Support\Facades\Auth;

class WithdrawalRepository implements WithdrawalRepositoryInterface {

    use ResponseTrait, AuthUserTrait;

    public function __construct(public WithdrawalAccount $account , protected PaystackService $paystackService,protected StripeService $stripeService)
    {
    }

    public function store($request) 
    {
        if ($this->user()->withdrawalAccounts()->count() > 0) {
            return $this->handleErrorResponse("User already has a withdrawal account");
        } 

        if (!Hash::check($request->pin, $this->user()->pin)) {
            return $this->handleErrorResponse("Invalid transaction PIN");
        }

        $medium = $this->medium();
        $recipient = null;

        try {
            switch ($medium) {
                case 'paystack':
                    $recipientData = $this->paystackService->createRecipent([
                        "type" => "nuban",
                        "name" => $request->account_name,
                        "account_number" => $request->account_number,
                        "bank_code" => $request->bank_code,
                        "currency" => $this->user()->country->currency ?? 'NGN',
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
                        "account_number" => $request->account_number,
                        "routing_number" => $request->bank_code, // Using bank_code as routing_number for Stripe
                    ]);
                    $recipientId = $recipientData->id;
                    break;
                
                default:
                    return $this->handleErrorResponse("Unsupported payment medium");
            }

            $account = $this->account->create([
                'bank_name' => $request->bank_name,
                'account_name' => $request->account_name,
                'meta' => [
                    'provider' => $medium,
                    "provider_id" => $recipientId
                ],
                'account_number' => $request->account_number,
                'routing_number' => $request->bank_code,
                'user_id' => $this->user()->id,
                'default' => true
            ]);

            return $this->handleSuccessResponse("Successfully created withdrawal account", $account);

        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function index() {
        $accounts = $this->user()->withdrawalAccounts()->paginate();
        return $this->handleSuccessResponse("Successfully fetched accounts", $accounts);
    }

    public function destroy($request) {
        $account = $this->user()->withdrawalAccounts()->find($request->id);
        if (!$account) {
            return $this->handleErrorResponse("Account not found");
        }
        $account->delete();
        return $this->handleSuccessResponse("Successfully deleted withdrawal account", []);
    }

    public function banks() {
        $banks = $this->paystackService->banks();
        return $this->handleSuccessResponse("Successfully fetched banks", $banks);
    }

    public function resolveBankAccount($request) {
        try {
            $account = $this->paystackService->resolveBankAccount([
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code
            ]);
            return $this->handleSuccessResponse("Successfully resolved account", $account);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function withdraw($request) {
        $user = $this->user();

        if (!Hash::check($request->pin, $user->pin)) {
            return $this->handleErrorResponse("Invalid transaction PIN");
        }

        $account = $user->withdrawalAccounts()->where('default', true)->first();
        if (!$account) {
            return $this->handleErrorResponse("No default withdrawal account found. Please add one.");
        }

        // Wallet check happens inside $user->withdraw(), but we can do a preliminary check
        if ($user->wallet->balance < $request->amount) {
            return $this->handleErrorResponse("Insufficient wallet balance");
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $account, $request) {
            try {
                $medium = $this->medium();
                $providerId = $account->meta['provider_id'];

                if ($medium === 'paystack') {
                    $this->paystackService->transfer([
                        "source" => "balance",
                        "amount" => $request->amount * 100, // Paystack uses kobo
                        "recipient" => $providerId,
                        "reason" => "Wallet Withdrawal"
                    ]);
                } else {
                    $this->stripeService->transfer([
                        "amount" => $request->amount * 100,
                        "destination" => $providerId,
                        "currency" => strtolower($user->country->currency ?? 'usd')
                    ]);
                }

                // Log the transaction and deduct balance
                $transaction = $user->withdraw($request->amount, "Withdrawal to {$account->account_name}");

                return $this->handleSuccessResponse("Withdrawal initiated successfully", $transaction);
            } catch (\Exception $e) {
                return $this->handleErrorResponse($e->getMessage());
            }
        });
    }

    protected function medium(): string
    {
        return ($this->user()->canUsePaystack()) ? "paystack" : "stripe";
    }

}