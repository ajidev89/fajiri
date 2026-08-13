<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasUuids;

    protected $fillable = [
        'donatable_id',
        'donatable_type',
        'user_id',
        'amount',
        'currency',
        'converted_amount',
        'base_amount_usd',
        'rate',
        'medium',
        'name',
        'email',
        'status',
        'reference',
    ];

    protected $casts = [
        'amount'          => 'float',
        'converted_amount' => 'float',
        'base_amount_usd' => 'float',
        'rate'            => 'float',
    ];

    public function donatable()
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
