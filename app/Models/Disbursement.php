<?php

namespace App\Models;

use App\Enums\Disbursement\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disbursement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'disbursable_id',
        'disbursable_type',
        'requested_by',
        'disbursed_by',
        'amount',
        'currency',
        'converted_amount',
        'rate',
        'beneficiary_name',
        'payment_method',
        'account_name',
        'account_number',
        'bank_name',
        'status',
        'proof_of_payment',
        'rejected_reason',
    ];

    protected $casts = [
        'status' => Status::class,
        'amount' => 'decimal:2',
    ];

    public function disbursable()
    {
        return $this->morphTo();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function disbursedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disbursed_by');
    }
}
