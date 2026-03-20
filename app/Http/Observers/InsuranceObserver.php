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
            $insurance->logo = app(CloudinaryService::class)->uploadImage(request()->file('logo'));
        }
    }

    public function updating(Insurance $insurance): void
    {   
        if(request()->hasFile('logo') && request()->file('logo')->isValid()) {
            $insurance->logo = app(CloudinaryService::class)->uploadImage(request()->file('logo'));
        }
    }

}
