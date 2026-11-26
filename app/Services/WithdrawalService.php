<?php

namespace App\Services;

use App\Enums\Kyc\Status as KycStatus;
use App\Enums\Withdrawal\Status;
use App\Http\Services\FirebaseNotification;
use App\Mail\WithdrawalStatusMail;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WithdrawalAccount;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Withdrawals are requests that an admin pays by hand.
 *
 * The amount leaves the wallet as soon as the member asks (a pending withdrawal
 * transaction), so it cannot be spent twice. Rejecting or cancelling puts it back;
 * marking the request paid completes the transaction.
 */
class WithdrawalService
{
    public function request(User $user, WithdrawalAccount $account, float $amount, ?string $note = null): WithdrawalRequest
    {
        $this->ensureCanWithdraw($user);

        $request = DB::transaction(function () use ($user, $account, $amount, $note) {
            $wallet = $user->wallet()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);

            if ($user->withdrawalRequests()->whereIn('status', [Status::PENDING, Status::APPROVED])->exists()) {
                throw new RuntimeException('You already have a withdrawal request in progress.');
            }

            if ((float) $wallet->balance < $amount) {
                throw new RuntimeException('Insufficient wallet balance');
            }

            $reference = 'WDR_'.Str::upper(Str::random(12));
            $currency = strtoupper((string) ($wallet->currency ?: $account->currency ?: $user->country?->currency ?: 'NGN'));

            $wallet->decrement('balance', $amount);

            $transaction = $wallet->transactions()->create([
                'amount' => $amount,
                'type' => 'withdrawal',
                'description' => "Withdrawal request to {$account->account_name}",
                'reference' => $reference,
                'status' => 'pending',
                'metadata' => ['withdrawal_request' => true],
            ]);

            return WithdrawalRequest::create([
                'reference' => $reference,
                'user_id' => $user->id,
                'withdrawal_account_id' => $account->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => Status::PENDING,
                'account_snapshot' => $account->payoutDetails(),
                'admin_note' => $note ? "Member note: {$note}" : null,
            ]);
        });

        $user->audit('withdrawal.requested', "Requested a withdrawal of {$request->currency} {$request->amount}", [
            'withdrawal_request_id' => $request->id,
        ], $user->id);

        $this->notifyMember($request, 'Withdrawal request received', "Your withdrawal request of {$this->money($request)} has been received and is awaiting review.");
        $this->notifyAdmins($request);

        return $request;
    }

    public function cancel(WithdrawalRequest $request, User $user): WithdrawalRequest
    {
        if ($request->user_id !== $user->id) {
            throw new RuntimeException('Withdrawal request not found.');
        }

        $request = $this->close($request, Status::CANCELLED, ['cancelled_at' => now()], [Status::PENDING]);

        $user->audit('withdrawal.cancelled', "Cancelled withdrawal {$request->reference}", ['withdrawal_request_id' => $request->id], $user->id);
        $this->notifyMember($request, 'Withdrawal cancelled', "Your withdrawal request of {$this->money($request)} was cancelled and the money is back in your wallet.");

        return $request;
    }

    public function approve(WithdrawalRequest $request, User $admin, ?string $note = null): WithdrawalRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $note) {
            $request = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== Status::PENDING) {
                throw new RuntimeException('Only pending withdrawal requests can be approved.');
            }

            $request->update([
                'status' => Status::APPROVED,
                'reviewed_by' => $admin->id,
                'approved_at' => now(),
                'admin_note' => $this->appendNote($request->admin_note, $note),
            ]);

            return $request;
        });

        $request->user->audit('withdrawal.approved', "Approved withdrawal {$request->reference}", ['withdrawal_request_id' => $request->id], $admin->id);
        $this->notifyMember($request, 'Withdrawal approved', "Your withdrawal of {$this->money($request)} has been approved and will be paid to your bank account shortly.");

        return $request;
    }

    public function reject(WithdrawalRequest $request, User $admin, string $reason): WithdrawalRequest
    {
        $request = $this->close($request, Status::REJECTED, [
            'reviewed_by' => $admin->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], [Status::PENDING, Status::APPROVED]);

        $request->user->audit('withdrawal.rejected', "Rejected withdrawal {$request->reference}", [
            'withdrawal_request_id' => $request->id,
            'reason' => $reason,
        ], $admin->id);
        $this->notifyMember($request, 'Withdrawal rejected', "Your withdrawal request of {$this->money($request)} was rejected: {$reason}. The money is back in your wallet.");

        return $request;
    }

    public function markPaid(WithdrawalRequest $request, User $admin, string $payoutReference, ?string $proofUrl = null, ?string $note = null): WithdrawalRequest
    {
        $request = DB::transaction(function () use ($request, $admin, $payoutReference, $proofUrl, $note) {
            $request = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $request->status->isOpen()) {
                throw new RuntimeException('Only pending or approved withdrawal requests can be marked as paid.');
            }

            $request->update([
                'status' => Status::PAID,
                'reviewed_by' => $admin->id,
                'approved_at' => $request->approved_at ?? now(),
                'paid_at' => now(),
                'payout_reference' => $payoutReference,
                'proof_url' => $proofUrl,
                'admin_note' => $this->appendNote($request->admin_note, $note),
            ]);

            Transaction::query()->whereKey($request->transaction_id)->update([
                'status' => 'completed',
                'description' => "Withdrawal to {$request->account_snapshot['account_name']}",
            ]);

            return $request;
        });

        $request->user->audit('withdrawal.paid', "Marked withdrawal {$request->reference} as paid", [
            'withdrawal_request_id' => $request->id,
            'payout_reference' => $payoutReference,
        ], $admin->id);
        $this->notifyMember($request, 'Withdrawal paid', "Your withdrawal of {$this->money($request)} has been paid to your bank account. Payment reference: {$payoutReference}.");

        return $request;
    }

    /**
     * Close an open request and put the held money back in the wallet.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, Status>  $from
     */
    private function close(WithdrawalRequest $request, Status $status, array $attributes, array $from): WithdrawalRequest
    {
        return DB::transaction(function () use ($request, $status, $attributes, $from) {
            $request = WithdrawalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! in_array($request->status, $from, true)) {
                throw new RuntimeException("This withdrawal request can no longer be {$status->value}.");
            }

            $wallet = $request->user->wallet()->lockForUpdate()->firstOrCreate(['user_id' => $request->user_id]);
            $wallet->increment('balance', (float) $request->amount);

            Transaction::query()->whereKey($request->transaction_id)->update(['status' => 'failed']);

            $request->update(['status' => $status] + $attributes);

            return $request;
        });
    }

    private function ensureCanWithdraw(User $user): void
    {
        if (! config('withdrawals.require_kyc')) {
            return;
        }

        $status = $user->kyc?->status;
        $status = $status instanceof KycStatus ? $status->value : $status;

        if ($status !== KycStatus::APPROVED->value) {
            throw new RuntimeException('Please complete identity verification (KYC) before withdrawing.');
        }
    }

    private function notifyMember(WithdrawalRequest $request, string $title, string $message): void
    {
        $user = $request->user;

        Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => 'withdrawal_'.$request->status->value,
            'data' => [
                'withdrawal_request_id' => $request->id,
                'reference' => $request->reference,
                'amount' => (float) $request->amount,
                'currency' => $request->currency,
                'status' => $request->status->value,
            ],
        ]);

        try {
            if (filled($user->email)) {
                Mail::to($user->email)->queue(new WithdrawalStatusMail($request, $title, $message));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to queue withdrawal email: '.$e->getMessage());
        }

        try {
            app(FirebaseNotification::class)->pushNotification($user, [
                'title' => $title,
                'description' => $message,
                'type' => 'withdrawal_'.$request->status->value,
            ], false);
        } catch (\Throwable $e) {
            Log::error('Failed to send withdrawal push: '.$e->getMessage());
        }
    }

    private function notifyAdmins(WithdrawalRequest $request): void
    {
        $inbox = config('withdrawals.admin_email');

        if (! filled($inbox)) {
            return;
        }

        try {
            Mail::to($inbox)->queue(new WithdrawalStatusMail(
                $request,
                'New withdrawal request',
                "{$request->user->email} requested a withdrawal of {$this->money($request)} (reference {$request->reference}). Review it in the admin dashboard.",
                forAdmin: true,
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to queue admin withdrawal email: '.$e->getMessage());
        }
    }

    private function appendNote(?string $existing, ?string $note): ?string
    {
        if (! filled($note)) {
            return $existing;
        }

        return trim(($existing ? $existing."\n" : '')."Admin: {$note}");
    }

    private function money(WithdrawalRequest $request): string
    {
        return $request->currency.' '.number_format((float) $request->amount, 2);
    }
}
