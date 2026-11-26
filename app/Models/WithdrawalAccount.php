<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalAccount extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'currency',
        'account_type',
        'bank_name',
        'bank_code',
        'account_name',
        'account_number',
        'routing_number',
        'iban',
        'swift_code',
        'sort_code',
        'bank_address',
        'account_holder_address',
        'meta',
        'default',
    ];

    /**
     * The bank details an admin needs to pay this account by hand.
     *
     * @return array<string, mixed>
     */
    public function payoutDetails(): array
    {
        return array_filter([
            'account_type' => $this->account_type,
            'country' => $this->country,
            'currency' => $this->currency,
            'bank_name' => $this->bank_name,
            'bank_code' => $this->bank_code,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'routing_number' => $this->routing_number,
            'iban' => $this->iban,
            'swift_code' => $this->swift_code,
            'sort_code' => $this->sort_code,
            'bank_address' => $this->bank_address,
            'account_holder_address' => $this->account_holder_address,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected $casts = [
        'meta' => 'array',
        'default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
