<?php

namespace App\Http\Observers;

use App\Jobs\Otp\SendOneTimePasswordJob;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;

class OtpObserver
{
    private const minutes = 5;

    /**
     * Handle the Otp "creating" event.
     */
    public function creating(Otp $otp): void
    {
        info( $otp);
        $otp->hash ??= Hash::make($otp->code);
        $otp->expired_at ??= now()->addMinutes(self::minutes);
    }

    // public function created(Otp $otp): void
    // {
   
    // }
}
