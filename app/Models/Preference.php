<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preference extends Model
{
    /** @use HasFactory<\Database\Factories\PreferenceFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'notification_sound',
        'auto_update_software',
        'community_updates',
        'project_updates',
        'event_updates',
        'receive_payment_confirmation',
        'membership_status_updates',
    ];

    protected $casts = [
        'notification_sound' => 'boolean',
        'auto_update_software' => 'boolean',
        'community_updates' => 'boolean',
        'project_updates' => 'boolean',
        'event_updates' => 'boolean',
        'receive_payment_confirmation' => 'boolean',
        'membership_status_updates' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
