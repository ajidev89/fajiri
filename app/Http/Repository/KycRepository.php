<?php

namespace App\Http\Repository;

use App\Enums\Kyc\Provider;
use App\Http\Repository\Contracts\KycRepositoryInterface;
use App\Enums\Verification\Status;
use App\Http\Resources\Verification\SessionResource;
use App\Http\Services\CloudinaryService;
use App\Http\Services\VeriffService;
use App\Http\Traits\ResponseTrait;
use App\Models\Document;
use App\Models\Kyc;
use App\Http\Traits\AuthUserTrait;
use App\Models\VerificationSession;

class KycRepository implements KycRepositoryInterface {

    use AuthUserTrait, ResponseTrait;
    public function __construct(protected Kyc $kyc, 
                                protected VerificationSession $verificationSession, 
                                protected VeriffService $veriffService,
                                protected Document $document,
                                protected CloudinaryService $cloudinary
    ) {
    }


    public function create(){

       $response = app(VeriffService::class)->createSession($this->user());  
       
       $verificationSession = $this->verificationSession->create([
            "user_id" => $this->user()->id,
            "provider" => Provider::VERIFF->value,
            "session_id" => $response["verification"]["id"],
            "status" => $response["verification"]["status"],
        ]);

       return $this->handleSuccessResponse("Verification session created", new SessionResource($verificationSession->fresh()));
    
    }


    public function uploadMedia(VerificationSession $session, $request){

        $file = $request->file('image');
        $response = app(VeriffService::class)->createSessionMedia($session->session_id,
            [     
                "image" => [
                    "context" => $request->type,
                    "content" => imageToBase64($file->getRealPath())
                ]
            ]
        );

        info($response);

        $url = $this->cloudinary->uploadImage($file);

        $document = $this->document->create([
            "user_id" => $this->user()->id,
            "type" => $request->type,
            "url" => $url,
            "provider" => $session->provider,
            "verification_session_id" => $session->id,
            "name" => $file->getClientOriginalName(),
            "mimetype" => $file->getClientMimeType(),
        ]);

        $session->update([
            "status" => Status::IN_PROGRESS
        ]);

       return $this->handleSuccessResponse("Successfully created document", $document);
    
    }   

    public function submitVerification(VerificationSession $session){

        $response = app(VeriffService::class)->updateSession($session->session_id, [
            "verification" => [
                "status" => "submitted"
            ]
        ]);

        $session->update([
            "status" => Status::COMPLETED
        ]);

        $this->user()->kyc()->update([
            "provider" => $session->provider,
            "verification_session_id" => $session->id,
            "status" => \App\Enums\Kyc\Status::PENDING
        ]);
        

        return $this->handleSuccessResponse("Successfully submitted kyc");
    }

}