<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollOption extends Model
{
    protected $fillable = [
        'poll_id',
        'label',
        'order',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    /**
     * Returns the vote count for this specific option.
     */
    public function getVotesCountAttribute(): int
    {
        return $this->responses()->count();
    }

    /**
     * Returns the percentage of votes this option received out of all poll responses.
     */
    public function getVotePercentageAttribute(): float
    {
        $total = $this->poll->responses()->count();
        if ($total === 0) {
            return 0;
        }
        return round(($this->responses()->count() / $total) * 100, 1);
    }
}
