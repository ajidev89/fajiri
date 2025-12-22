<?php

namespace App\Jobs\Otp;

use App\Http\Services\TwilioService;
use App\Mail\Otp\OneTimePasswordMail;
use App\Models\Otp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendOneTimePasswordJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Otp $otp, public $code)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        switch ($this->otp->channel) {
            case 'email':
                Mail::to($this->otp->identifier)->send(new OneTimePasswordMail($this->code));
                break;

            case 'phone':
                TwilioService::sendVerificationSms($this->code);
                break;

            default:
                // code...
                break;
        }
    }
}
