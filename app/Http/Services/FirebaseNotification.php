<?php
namespace App\Http\Services;
use App\Models\User;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseNotification {

    public PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(config("services.firebase.baseurl", "https://fcm.googleapis.com/v1/projects/"));
    }

    protected function handleResponse($response){
        $body = $response->json();
        if(!$response->successful()){
            throw new Exception($body['message'] ?? 'FCM Request Failed');
        }
        return $body;
    }

    public function getAccessToken() {
        $serviceJsonPath = storage_path('firebase/service.json');
        if (!file_exists($serviceJsonPath)) {
            throw new Exception("Firebase service JSON not found at: " . $serviceJsonPath);
        }

        $jsonKey = json_decode(file_get_contents($serviceJsonPath), true);
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountCredentials($scopes, $jsonKey);
        $accessToken = $credentials->fetchAuthToken();

        return $accessToken['access_token'];
    }

    public function createNotification($token , $payload){
        $projectId = config("services.firebase.projectId");
        $this->http->withToken($this->getAccessToken())->post($projectId . "/messages:send",[
            "message" => [
                "token" => $token,
                "notification" => $payload
            ]
        ]);
    }

    public function pushNotificationBatch(array $users, array $payload = ["title" => "", "type" => "", "description" => ""])
    {
        $tokens = collect($users)
            ->pluck('notification_token')
            ->filter()                    
            ->unique()                     
            ->values()
            ->all(); 

        info("sending notification");
        info($tokens);

        if (empty($tokens)) return;

        try {
            $projectId = config("services.firebase.projectId");
            $this->http
                ->withToken($this->getAccessToken())
                ->post($projectId . "/messages:send", [
                    "message" => [
                        "tokens" => $tokens,
                        "notification" => [
                            'title' => $payload['title'],
                            'body'  => strip_tags($payload['description'])
                        ]
                    ]
                ]);

        } catch (Exception $e) {
            logger()->error("Batch notification error: " . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    public function pushNotification(User $user, $payload = ["title" => "", "type" => "", "description" => "" ], $create = true){
        try {
            if($user->notification_token) {
               $this->createNotification($user->notification_token, [
                    'title' => $payload['title'], 
                    'body' => strip_tags($payload['description'])
                ]);
            }

            if($create){
                $user->notifications()->create([
                    "title" => $payload['title'],
                    "description" => $payload['description'],
                    "type" => $payload["type"]
                ]);
            }

        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Subscribe a token to a topic
     */
    public function subscribeToTopic(string $token, string $topic)
    {
        return $this->subscribeBatchToTopic([$token], $topic);
    }

    /**
     * Unsubscribe a token from a topic
     */
    public function unsubscribeFromTopic(string $token, string $topic)
    {
        return $this->unsubscribeBatchFromTopic([$token], $topic);
    }

    /**
     * Subscribe multiple tokens to a topic
     */
    public function subscribeBatchToTopic(array $tokens, string $topic)
    {
        if (empty($tokens)) return;

        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'access_token_auth' => 'true'
                ])
                ->post("https://iid.googleapis.com/iid/v1:batchAdd", [
                    "to" => "/topics/{$topic}",
                    "registration_tokens" => $tokens
                ]);

            return $response->json();
        } catch (Exception $e) {
            logger()->error("FCM Topic subscription error: " . $e->getMessage());
        }
    }

    /**
     * Unsubscribe multiple tokens from a topic
     */
    public function unsubscribeBatchFromTopic(array $tokens, string $topic)
    {
        if (empty($tokens)) return;

        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'access_token_auth' => 'true'
                ])
                ->post("https://iid.googleapis.com/iid/v1:batchRemove", [
                    "to" => "/topics/{$topic}",
                    "registration_tokens" => $tokens
                ]);

            return $response->json();
        } catch (Exception $e) {
            logger()->error("FCM Topic unsubscription error: " . $e->getMessage());
        }
    }

    /**
     * Send a notification to a topic
     */
    public function sendToTopic(string $topic, array $payload)
    {
        return $this->sendToCondition("'{$topic}' in topics", $payload);
    }

    /**
     * Send a notification with a condition (e.g. exclude current user)
     */
    public function sendToCondition(string $condition, array $payload)
    {
        try {
            $projectId = config("services.firebase.projectId");
            $this->http->withToken($this->getAccessToken())->post($projectId . "/messages:send",[
                "message" => [
                    "condition" => $condition,
                    "notification" => [
                        'title' => $payload['title'],
                        'body'  => strip_tags($payload['description'] ?? $payload['body'] ?? '')
                    ],
                    "data" => $payload['data'] ?? []
                ]
            ]);
        } catch (Exception $e) {
            logger()->error("FCM Condition send error: " . $e->getMessage());
        }
    }
}
