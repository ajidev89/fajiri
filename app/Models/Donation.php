<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasUuids;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'amount',
        'currency',
        'converted_amount',
        'rate',
        'status',
        'reference',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
