<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Traits\ResponseTrait;
use App\Models\Otp;
use Illuminate\Support\Facades\Crypt;

class OtpRepository implements OtpRepositoryInterface
{
    use ResponseTrait;

    public function __construct(protected Otp $otp) {}

    public function create($request)
    {

        $this->otp->create([
            'identifier' => $request->identifier,
            'channel' => $request->channel,
            'code' => random_int(100000, 999999),
        ]);

        return $this->handleSuccessResponse('Successfully sent otp', [], 204);
    }


    public function verify($request)
    {
        $request->fufill();
        return $this->handleSuccessResponse('Successfully verified otp', [
            "token" => generateToken([
                'value' => $request->identifier,
                'channel' => $request->channel
            ])
        ]);
    }
}
