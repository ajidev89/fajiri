<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\KycRepositoryInterface;
use App\Http\Requests\Verification\UploadMediaRequest;
use App\Models\VerificationSession;

class KycController extends Controller
{
    public KycRepositoryInterface $kycRepositoryInterface;

    public function __construct(KycRepositoryInterface $kycRepositoryInterface)
    {
        $this->kycRepositoryInterface = $kycRepositoryInterface;
    }

    public function create(){
        return $this->kycRepositoryInterface->create();
    }

    public function uploadMedia(VerificationSession $session, UploadMediaRequest $request){
        return $this->kycRepositoryInterface->uploadMedia($session, $request);
    }

    public function submitVerification(VerificationSession $session){
        return $this->kycRepositoryInterface->submitVerification($session); 
    }
}
