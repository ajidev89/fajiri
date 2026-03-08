<?php

namespace App\Http\Observers;

use App\Http\Services\CloudinaryService;
use App\Models\Campaign;

class CampaignObserver
{
    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Campaign $campaign): void
    {
        $campaign->images = app(CloudinaryService::class)->uploadMultiple($campaign->images);
    }

    public function updating(Campaign $campaign): void
    {
        if ($campaign->isDirty('images')) {
            $campaign->images = app(CloudinaryService::class)->uploadMultiple($campaign->images);
        }
    }

}
