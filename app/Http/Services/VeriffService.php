<?php
namespace App\Http\Services;

use App\Enums\Kyc\Status;
use App\Http\Traits\ResponseTrait;
use App\Jobs\Kyc\VerificationJob;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VeriffService {

    use ResponseTrait;

    protected PendingRequest $http;

    public function __construct(protected User $user)
    {
        $this->http = Http::baseUrl(config("services.veriff.baseurl"))->withHeaders([
            "X-AUTH-CLIENT" =>  config("services.veriff.apiKey")
        ]);
    }

    public function getSession($id)
    {
        $response  = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC($id)
        ])->get("/v1/sessions/{$id}");

        return $response->throw()->json();
    }

    public function verifySession($id)
    {
        $response  = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC($id)
        ])->get("/v1/sessions/{$id}/decision");

        return $response->throw()->json();
    }

    public function createSession(User $user)
    {

        $response  = $this->http->post("/v1/sessions",[
            "verification" => [
                "vendorData" => $user->id,
                "callback" => null,
                "person" => [
                    "firstName" => $user->profile->first_name,
                    "lastName" => $user->profile->last_name
                ],
                "address" => [
                    "fullAddress" => "{$user->address->line_1}, {$user->address->line_2}, {$user->address->city}, {$user->address->state}, {$user->address->country->name}",
                ]
            ]
        ]);

        return $response->throw()->json();
    }

    public function updateSession($id, $data = [])
        {
        $response  = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC(json_encode($data))
        ])->patch("/v1/sessions/{$id}", $data);

        return $response->throw()->json();
    }


    public function deleteSession($id)
    {
        $response = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC($id)
        ])->delete("/v1/sessions/{$id}");

        return $response->throw()->json();
    }


    public function getSessionMedia($id) {

        $response = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC($id)
        ])->get("/v1/sessions/{$id}/media");

        return $response->throw()->json();
    }

    public function createSessionMedia($id, $data = []) {

        $response  = $this->http->withHeaders([
            "X-HMAC-SIGNATURE" => $this->generateHMAC(json_encode($data))
        ])->post("/v1/sessions/{$id}/media", $data);

        return $response->throw()->json();
    }

    public function getImage($id) {

        $response  = Http::withHeaders([
            "X-AUTH-CLIENT" =>  config("services.veriff.apiKey"),
            "X-HMAC-SIGNATURE" => $this->generateHMAC($id)
        ])->get("https://api.saas-4.veriff.me/v1/media/{$id}");


        if ($response->successful()) {
            $content = $response->body();
            $contentType = $response->header('Content-Type');
            return response($content, 200)
                ->header('Content-Type', $contentType);
        }

        return response()->json(['error' => 'Unable to retrieve image'], $response->status());
    }

    public function generateHMAC($request){ 
        return hash_hmac('sha256', $request , config('services.veriff.sigKey'));
    }


    public function handleWebhook(Request $request) {

        $signature = $request->header('x-hmac-signature');

        $data = json_decode($request->getContent(),false,512,JSON_OBJECT_AS_ARRAY);

        $jsonString = json_encode($data,JSON_HEX_TAG);   

        $generatedHash = hash_hmac('sha256', $jsonString , config('services.veriff.sigKey'));
    
        $isValid = hash_equals(strtolower($signature), strtolower($generatedHash));
    
        if (!$isValid) {
            return $this->handleErrorResponse("Cannot verify payload", 403);
        }

        if(isset($request->verification)){
            $user = $this->user->where('id', $request->verification['vendorData'])->firstOrFail();

            $status =  Status::values();

            if(in_array(strtolower($request->verification['status']), $status)){
                $user->kyc()->update([
                    'status' => strtolower($request->verification['status']),
                ]);
        
                VerificationJob::dispatch($user ,strtolower($request->verification['status']));
            }

            return $this->handleSuccessResponse("Decision made");
        }

    }


    public function handleDecision($status){

        switch ($status) {

            case 'approved':
                
            case 'resubmission':


            case 'declined':


            default:
                # code...
                break;
        }
    }
    
}