<?php

namespace App\Http\Observers;

use App\Http\Services\CloudinaryService;
use App\Models\Insurance;

class InsuranceObserver
{
    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Insurance $insurance): void
    {
        if(request()->hasFile('logo')) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('logo'));
            $insurance->logo = $upload['url'];
        }
    }

    public function updating(Insurance $insurance): void
    {   
        if(request()->hasFile('logo') && request()->file('logo')->isValid()) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('logo'));
            $insurance->logo = $upload['url'];
        }
    }

}
