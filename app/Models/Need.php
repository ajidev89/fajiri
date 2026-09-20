<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Need extends Model
{
    use HasUuids, Observable;

    protected $fillable = [
        'name',
        'age',
        'location',
        'currency',
        'amount',
        'description',
        'image',
        'urgency',
        'added_by',
    ];

    protected $appends = [
        'collected_amount',
    ];

    public function donations(): MorphMany
    {
        return $this->morphMany(Donation::class, 'donatable');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function getCollectedAmountAttribute(): float
    {
        if (array_key_exists('donations_sum_converted_amount', $this->attributes)) {
            return (float) $this->attributes['donations_sum_converted_amount'];
        }

        return (float) $this->donations()->where('status', 'completed')->sum('converted_amount');
    }
}
