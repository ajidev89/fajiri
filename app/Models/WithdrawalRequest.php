<?php

namespace App\Models;

use App\Enums\Withdrawal\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'reference',
        'user_id',
        'withdrawal_account_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'account_snapshot',
        'reviewed_by',
        'payout_reference',
        'proof_url',
        'admin_note',
        'rejection_reason',
        'approved_at',
        'paid_at',
        'rejected_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => Status::class,
            'account_snapshot' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(WithdrawalAccount::class, 'withdrawal_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
