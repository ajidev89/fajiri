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
            $uploads = app(CloudinaryService::class)->uploadMultiple(request()->file('images'));
            $campaign->images = array_column($uploads, 'url');
        }
        $campaign->end_date = now()->addDays(intval(request()->days));
    }

    public function updating(Campaign $campaign): void
    {   
        if(request()->hasFile('images')) {
            $uploads = app(CloudinaryService::class)->uploadMultiple(request()->file('images'));
            $campaign->images = array_column($uploads, 'url');
        }
        if(request()->has('days')) {
            $campaign->end_date = now()->addDays(intval(request()->days));
        }
    }

}
