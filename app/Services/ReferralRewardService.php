<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralRewardService
{
    public const BONUS_AMOUNT_NGN = 5000;

    public const DESCRIPTION = 'Referral bonus';

    public function __construct(protected CurrencyService $currencyService) {}

    public function recordPending(User $referredUser): void
    {
        if (! $referredUser->referred_by || $this->rewardTransaction($referredUser) !== null) {
            return;
        }

        $referrer = User::query()->find($referredUser->referred_by);

        if ($referrer === null) {
            return;
        }

        $wallet = $this->referrerWallet($referrer);

        $wallet->transactions()->create([
            'amount' => $this->bonusAmount($wallet),
            'type' => 'deposit',
            'description' => self::DESCRIPTION,
            'reference' => 'REF-'.Str::upper(Str::random(10)),
            'status' => 'pending',
            'metadata' => [
                'referred_user_id' => $referredUser->id,
            ],
        ]);
    }

    public function releaseIfEligible(User $referredUser): void
    {
        if (! $referredUser->referred_by || ! $this->hasActivePlan($referredUser)) {
            return;
        }

        DB::transaction(function () use ($referredUser) {
            $referrer = User::query()->find($referredUser->referred_by);

            if ($referrer === null) {
                return;
            }

            $wallet = $referrer->wallet()->lockForUpdate()->first();

            if ($wallet === null) {
                return;
            }

            $existing = $this->rewardTransaction($referredUser, $wallet->id);

            if ($existing !== null && $existing->status !== 'pending') {
                return;
            }

            if ($existing === null) {
                $amount = $this->bonusAmount($wallet);
                $wallet->increment('balance', $amount);
                $wallet->transactions()->create([
                    'amount' => $amount,
                    'type' => 'deposit',
                    'description' => self::DESCRIPTION,
                    'reference' => 'REF-'.Str::upper(Str::random(10)),
                    'status' => 'completed',
                    'metadata' => [
                        'referred_user_id' => $referredUser->id,
                    ],
                ]);

                return;
            }

            $wallet->increment('balance', $existing->amount);
            $existing->update(['status' => 'completed']);
        });
    }

    private function hasActivePlan(User $user): bool
    {
        return $user->plans()
            ->where('user_plans.status', 'active')
            ->where('plans.price', '>', 0)
            ->where(function ($query) {
                $query->whereNull('user_plans.expires_at')
                    ->orWhere('user_plans.expires_at', '>', now());
            })
            ->exists();
    }

    private function rewardTransaction(User $referredUser, ?string $walletId = null): ?Transaction
    {
        return Transaction::query()
            ->when($walletId !== null, fn ($query) => $query->where('wallet_id', $walletId))
            ->where('description', self::DESCRIPTION)
            ->where('metadata->referred_user_id', $referredUser->id)
            ->first();
    }

    private function referrerWallet(User $referrer): Wallet
    {
        $currency = $referrer->wallet?->currency
            ?? $referrer->country?->currency
            ?? 'NGN';

        return $referrer->wallet()->firstOrCreate(
            ['user_id' => $referrer->id],
            ['currency' => $currency, 'balance' => 0]
        );
    }

    private function bonusAmount(Wallet $wallet): float
    {
        return $this->currencyService->convert(self::BONUS_AMOUNT_NGN, 'NGN', $wallet->currency ?: 'NGN');
    }
}
