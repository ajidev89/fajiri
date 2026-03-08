<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'status',
        'images',
        'goal_amount',
    ];

    protected $casts = [
        'images' => 'array',
        'goal_amount' => 'float',
        'type' => \App\Enums\Campagin\Type::class,
        'status' => \App\Enums\Campagin\Status::class,
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected $appends = [
        'collected_amount',
        'donors_count',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function getCollectedAmountAttribute(): float
    {
        return $this->donations()->where('status', 'completed')->sum('amount');
    }

    public function getDonorsCountAttribute(): int
    {
        return $this->donations()->where('status', 'completed')->distinct('user_id')->count('user_id');
    }
}
