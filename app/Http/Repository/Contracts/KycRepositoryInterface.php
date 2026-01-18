<?php

namespace App\Http\Repository\Contracts;

use App\Models\VerificationSession;


interface KycRepositoryInterface { 
    public function create();
    public function uploadMedia(VerificationSession $session, $request);
    public function submitVerification(VerificationSession $session);
}