<?php

namespace App\Traits;

use App\Models\Transaction;
use App\Models\Wallet;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

trait HasWallet
{
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function deposit(float $amount, string $description = null, string $reference = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception("Deposit amount must be greater than zero.");
        }

        return DB::transaction(function () use ($amount, $description, $reference) {
            $wallet = $this->wallet()->firstOrCreate(['user_id' => $this->id]);
            $wallet->increment('balance', $amount);

            return $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'deposit',
                'description' => $description,
                'reference' => $reference ?? 'DEP_' . uniqid(),
                'status' => 'completed',
            ]);
        });
    }

    public function withdraw(float $amount, string $description = null, string $reference = null): Transaction
    {
        if ($amount <= 0) {
            throw new Exception("Withdrawal amount must be greater than zero.");
        }

        return DB::transaction(function () use ($amount, $description, $reference) {
            $wallet = $this->wallet()->firstOrCreate(['user_id' => $this->id]);

            if ($wallet->balance < $amount) {
                throw new Exception("Insufficient wallet balance.");
            }

            $wallet->decrement('balance', $amount);

            return $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'withdrawal',
                'description' => $description,
                'reference' => $reference ?? 'WTH_' . uniqid(),
                'status' => 'completed',
            ]);
        });
    }

    public function getBalanceAttribute(): float
    {
        return $this->wallet ? $this->wallet->balance : 0.0;
    }
}
