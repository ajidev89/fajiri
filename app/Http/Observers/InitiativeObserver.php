<?php

namespace App\Http\Observers;

use App\Http\Services\CloudinaryService;
use App\Models\Initiative;

class InitiativeObserver
{
    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Initiative $initiative): void
    {
        if(request()->hasFile('image')) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('image'));
            $initiative->image = $upload['url'];
        }
    }

    public function updating(Initiative $initiative): void
    {   
        if(request()->hasFile('image') && request()->file('image')->isValid()) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('image'));
            $initiative->image = $upload['url'];
        }
    }

}
