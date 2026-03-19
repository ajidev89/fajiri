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
            $initiative->image = app(CloudinaryService::class)->uploadImage(request()->file('image'));
        }
    }

    public function updating(Initiative $initiative): void
    {   
        if(request()->hasFile('image') && request()->file('image')->isValid()) {
            $initiative->image = app(CloudinaryService::class)->uploadImage(request()->file('image'));
        }
    }

}
