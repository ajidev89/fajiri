<?php

namespace App\Models;

use App\Enums\Disbursement\PayoutMethod;
use App\Enums\Disbursement\RecipientType;
use App\Enums\Disbursement\RiskLevel;
use App\Enums\Disbursement\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Disbursement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'disbursement_code',
        'disbursable_id',
        'disbursable_type',
        'requested_by',
        'disbursed_by',
        'recipient_type',
        'recipient_country',
        'recipient_email',
        'recipient_phone',
        'amount',
        'fee_amount',
        'fee_bearer',
        'net_amount',
        'currency',
        'target_currency',
        'converted_amount',
        'rate',
        'estimated_recipient_amount',
        'beneficiary_name',
        'payment_method',
        'account_name',
        'account_number',
        'destination_mask',
        'bank_name',
        'bank_code',
        'routing_number',
        'swift_bic',
        'iban',
        'status',
        'purpose',
        'purpose_description',
        'documents',
        'compliance_checks',
        'risk_score',
        'risk_level',
        'security_auth_method',
        'payout_provider',
        'provider_reference',
        'provider_response',
        'proof_of_payment',
        'rejected_reason',
        'status_history',
        'audit_trail',
    ];

    protected $casts = [
        'status'                     => Status::class,
        'recipient_type'             => RecipientType::class,
        'payout_method'              => PayoutMethod::class,
        'risk_level'                 => RiskLevel::class,
        'amount'                     => 'decimal:2',
        'fee_amount'                 => 'decimal:2',
        'net_amount'                 => 'decimal:2',
        'converted_amount'           => 'decimal:2',
        'rate'                       => 'decimal:8',
        'estimated_recipient_amount' => 'decimal:2',
        'documents'                  => 'array',
        'compliance_checks'          => 'array',
        'provider_response'          => 'array',
        'status_history'             => 'array',
        'audit_trail'                => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->disbursement_code)) {
                $year = date('Y');
                $count = static::whereYear('created_at', $year)->count() + 1;
                $model->disbursement_code = sprintf('DSB-%s-%06d', $year, $count);
            }

            if (empty($model->destination_mask) && !empty($model->account_number)) {
                $len = strlen($model->account_number);
                $last4 = substr($model->account_number, max(0, $len - 4));
                $model->destination_mask = ($model->bank_name ? $model->bank_name . ' ' : '') . '•••• ' . $last4;
            }

            if (empty($model->status_history)) {
                $model->status_history = [[
                    'status'    => ($model->status instanceof Status ? $model->status->value : $model->status) ?: Status::PENDING->value,
                    'actor_id'  => $model->requested_by,
                    'timestamp' => now()->toIso8601String(),
                    'note'      => 'Disbursement request initiated',
                ]];
            }
        });
    }

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

    /**
     * Record a transition in status history
     */
    public function recordStatusTransition(Status $newStatus, ?string $actorId = null, ?string $note = null): self
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status'    => $newStatus->value,
            'actor_id'  => $actorId,
            'timestamp' => now()->toIso8601String(),
            'note'      => $note,
        ];

        $this->status = $newStatus;
        $this->status_history = $history;

        return $this;
    }
}

