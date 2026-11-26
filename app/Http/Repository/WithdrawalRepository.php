<?php

namespace App\Http\Repository;

use App\Enums\Withdrawal\Status;
use App\Http\Repository\Contracts\WithdrawalRepositoryInterface;
use App\Http\Resources\Withdrawal\WithdrawalRequestResource;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\WithdrawalAccount;
use App\Services\PaystackService;
use App\Services\StripeService;
use App\Services\WithdrawalService;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class WithdrawalRepository implements WithdrawalRepositoryInterface
{
    use AuthUserTrait, ResponseTrait;

    public function __construct(
        public WithdrawalAccount $account,
        protected PaystackService $paystackService,
        protected StripeService $stripeService,
        protected WithdrawalService $withdrawals,
    ) {}

    public function store($request)
    {
        if ($this->user()->withdrawalAccounts()->count() > 0) {
            return $this->handleErrorResponse('User already has a withdrawal account');
        }

        if (! Hash::check($request->pin, $this->user()->pin)) {
            return $this->handleErrorResponse('Invalid transaction PIN');
        }

        $account = $this->account->create($request->accountData() + [
            'user_id' => $this->user()->id,
            'default' => true,
            'meta' => ['provider' => 'manual'],
        ]);

        return $this->handleSuccessResponse('Successfully created withdrawal account', $account);
    }

    public function update($request)
    {
        $account = $this->user()->withdrawalAccounts()->find($request->route('id'));
        if (! $account) {
            return $this->handleErrorResponse('Account not found', 404);
        }

        if (! Hash::check($request->pin, $this->user()->pin)) {
            return $this->handleErrorResponse('Invalid transaction PIN');
        }

        if ($this->user()->withdrawalRequests()->whereIn('status', [Status::PENDING, Status::APPROVED])->exists()) {
            return $this->handleErrorResponse('You cannot change your bank account while a withdrawal is in progress.');
        }

        $account->update($request->accountData() + ['meta' => ['provider' => 'manual']]);

        return $this->handleSuccessResponse('Successfully updated withdrawal account', $account->fresh());
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

        if ($this->user()->withdrawalRequests()->whereIn('status', [Status::PENDING, Status::APPROVED])->exists()) {
            return $this->handleErrorResponse('You cannot remove your bank account while a withdrawal is in progress.');
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

        try {
            $withdrawal = $this->withdrawals->request($user, $account, (float) $request->amount, $request->input('note'));
        } catch (RuntimeException $e) {
            return $this->handleErrorResponse($e->getMessage());
        }

        return $this->handleSuccessResponse(
            'Withdrawal request submitted. You will be notified once it is paid.',
            new WithdrawalRequestResource($withdrawal),
            201
        );
    }

    public function requests($request)
    {
        $requests = $this->user()->withdrawalRequests()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return WithdrawalRequestResource::collection($requests)->additional([
            'message' => 'Successfully fetched withdrawal requests',
            'status' => true,
        ]);
    }

    public function cancel($request)
    {
        $withdrawal = $this->user()->withdrawalRequests()->find($request->route('id'));
        if (! $withdrawal) {
            return $this->handleErrorResponse('Withdrawal request not found', 404);
        }

        try {
            $withdrawal = $this->withdrawals->cancel($withdrawal, $this->user());
        } catch (RuntimeException $e) {
            return $this->handleErrorResponse($e->getMessage());
        }

        return $this->handleSuccessResponse('Withdrawal request cancelled', new WithdrawalRequestResource($withdrawal));
    }
}
