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
        if(request()->hasFile('images')) {
            $campaign->images = app(CloudinaryService::class)->uploadMultiple(request()->file('images'));
        }
    }

    public function updating(Campaign $campaign): void
    {   
        if(request()->hasFile('images')) {
            $campaign->images = app(CloudinaryService::class)->uploadMultiple(request()->file('images'));
        }
    }

}
