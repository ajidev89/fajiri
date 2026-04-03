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
        $campaign->end_date = now()->addDays(intval(request()->days));
    }

    public function updating(Campaign $campaign): void
    {   
        if(request()->hasFile('images') && request()->file('images')->isValid()) {
            $campaign->images = app(CloudinaryService::class)->uploadMultiple(request()->file('images'));
        }
        if(request()->has('days')) {
            $campaign->end_date = now()->addDays(intval(request()->days));
        }
    }

}
