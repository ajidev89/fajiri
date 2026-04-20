<?php

namespace App\Models;

use App\Http\Traits\Observable;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
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

    public function donations(): MorphMany
    {
        return $this->morphMany(Donation::class, 'donatable');
    }

    public function addedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
