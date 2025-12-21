<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\OtpRepositoryInterface;
use App\Http\Requests\Otp\CreateRequest;
use App\Http\Requests\Otp\VerifyRequest;

class OtpController extends Controller
{
    public OtpRepositoryInterface $otpRepositoryInterface;

    public function __construct(OtpRepositoryInterface $otpRepositoryInterface)
    {
        $this->otpRepositoryInterface = $otpRepositoryInterface;
    }

    public function index(CreateRequest $request){
        return $this->otpRepositoryInterface->create($request);
    }

    public function verify(VerifyRequest $request){
        return $this->otpRepositoryInterface->verify($request);
    }
}
