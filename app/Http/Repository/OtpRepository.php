<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Traits\ResponseTrait;
use App\Jobs\Otp\SendOneTimePasswordJob;
use App\Models\Otp;
use Illuminate\Support\Facades\Hash;

class OtpRepository implements OtpRepositoryInterface
{
    use ResponseTrait;

    public function __construct(protected Otp $otp) {}

    public function create($request)
    {

        $code = random_int(100000, 999999);

        $otp = $this->otp->create([
            'identifier' => $request->identifier,
            'channel' => $request->channel,
            'hash' => Hash::make($code),
        ]);

        SendOneTimePasswordJob::dispatchAfterResponse($otp, $code);

        return $this->handleSuccessResponse('Successfully sent otp', [], 204);
    }


    public function verify($request)
    {
        $request->fulfill();
        return $this->handleSuccessResponse('Successfully verified otp', [
            "token" => generateToken([
                'value' => $request->identifier,
                'channel' => $request->channel
            ])
        ]);
    }
}
