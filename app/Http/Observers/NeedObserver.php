<?php

namespace App\Http\Observers;

use App\Http\Services\CloudinaryService;
use App\Models\Need;

class NeedObserver
{
    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Need $need): void
    {
        if(request()->hasFile('image')) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('image'));
            $need->image = $upload['url'];
        }
    }

    public function updating(Need $need): void
    {   
        if(request()->hasFile('image') && request()->file('image')->isValid()) {
            $upload = app(CloudinaryService::class)->uploadImage(request()->file('image'));
            $need->image = $upload['url'];
        }
    }

}
