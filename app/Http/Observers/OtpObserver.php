<?php

namespace App\Http\Observers;

use App\Models\Otp;

class OtpObserver
{
    private const minutes = 5;

    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Otp $otp): void
    {
        $otp->expires_at ??= now()->addMinutes(self::minutes);
    }

    // public function created(Otp $otp): void
    // {
   
    // }
}
