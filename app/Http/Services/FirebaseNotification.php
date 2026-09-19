<?php

namespace App\Http\Services;

use App\Models\User;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FirebaseNotification
{
    public PendingRequest $http;

    public function __construct()
    {
        $this->http = Http::baseUrl(config('services.firebase.baseurl', 'https://fcm.googleapis.com/v1/projects/'));
    }

    protected function handleResponse($response)
    {
        $body = $response->json();
        if (! $response->successful()) {
            throw new Exception($body['message'] ?? 'FCM Request Failed');
        }

        return $body;
    }

    protected function serviceAccount(): array
    {
        $serviceJsonPath = config('services.firebase.credentials', storage_path('firebase/service.json'));
        if (! file_exists($serviceJsonPath)) {
            throw new Exception('Firebase service JSON not found at: '.$serviceJsonPath);
        }

        return json_decode(file_get_contents($serviceJsonPath), true);
    }

    public function getAccessToken()
    {
        // Google access tokens live for 60 minutes; reuse one instead of minting a token per request.
        return Cache::remember('firebase:fcm_access_token', now()->addMinutes(50), function () {
            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = new ServiceAccountCredentials($scopes, $this->serviceAccount());
            $accessToken = $credentials->fetchAuthToken();

            if (empty($accessToken['access_token'])) {
                throw new Exception('Unable to obtain Firebase access token');
            }

            return $accessToken['access_token'];
        });
    }

    public function getProjectId(): string
    {
        $projectId = config('services.firebase.projectId') ?: ($this->serviceAccount()['project_id'] ?? null);
        if (! $projectId) {
            throw new Exception('Firebase project ID is not configured (set FIREBASE_PROJECT_ID)');
        }

        return $projectId;
    }

    public function createNotification($token, $payload)
    {
        $projectId = $this->getProjectId();
        $this->http->withToken($this->getAccessToken())->post($projectId.'/messages:send', [
            'message' => [
                'token' => $token,
                'notification' => $payload,
            ],
        ]);
    }

    /**
     * Send a push notification to every device token belonging to the given users.
     *
     * FCM HTTP v1 has no multicast endpoint, so each token gets its own request.
     * Requests are sent concurrently in groups. Tokens that FCM reports as no
     * longer registered are cleared from the user record so we stop sending to them.
     *
     * @param  array<int, User>  $users
     * @param  array{title?: string, description?: string, type?: string, image?: ?string, data?: array}  $payload
     * @return array{sent: int, failed: int, invalid: int}
     */
    public function pushNotificationBatch(array $users, array $payload = ['title' => '', 'type' => '', 'description' => ''])
    {
        $tokens = collect($users)
            ->pluck('notification_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $result = ['sent' => 0, 'failed' => 0, 'invalid' => 0];

        if (empty($tokens)) {
            return $result;
        }

        $url = rtrim(config('services.firebase.baseurl', 'https://fcm.googleapis.com/v1/projects/'), '/')
            .'/'.$this->getProjectId().'/messages:send';
        $accessToken = $this->getAccessToken();

        $notification = array_filter([
            'title' => $payload['title'] ?? '',
            'body' => strip_tags($payload['description'] ?? ''),
            'image' => $payload['image'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // FCM requires every data value to be a string.
        $data = collect(array_merge(['type' => $payload['type'] ?? ''], $payload['data'] ?? []))
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))
            ->all();

        $invalidTokens = [];

        foreach (array_chunk($tokens, (int) config('services.firebase.concurrency', 50)) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => collect($chunk)->map(
                fn ($token) => $pool->as($token)
                    ->withToken($accessToken)
                    ->timeout(15)
                    ->post($url, [
                        'message' => [
                            'token' => $token,
                            'notification' => $notification,
                            'data' => (object) $data,
                            'android' => ['priority' => 'high'],
                            'apns' => ['headers' => ['apns-priority' => '10']],
                        ],
                    ])
            )->all());

            foreach ($responses as $token => $response) {
                if ($response instanceof \Throwable) {
                    $result['failed']++;
                    logger()->warning('FCM send exception: '.$response->getMessage());

                    continue;
                }

                if ($response->successful()) {
                    $result['sent']++;

                    continue;
                }

                $error = $response->json('error') ?? [];
                $errorCode = collect($error['details'] ?? [])->pluck('errorCode')->filter()->first()
                    ?? ($error['status'] ?? null);

                if ($response->status() === 404 || $errorCode === 'UNREGISTERED') {
                    $result['invalid']++;
                    $invalidTokens[] = $token;
                } else {
                    $result['failed']++;
                    logger()->warning('FCM send failed', [
                        'status' => $response->status(),
                        'error' => $error['message'] ?? $response->body(),
                    ]);
                }
            }
        }

        if (! empty($invalidTokens)) {
            User::whereIn('notification_token', $invalidTokens)->update(['notification_token' => null]);
        }

        logger()->info('FCM batch push complete', $result + ['tokens' => count($tokens)]);

        return $result;
    }

    public function pushNotification(User $user, $payload = ['title' => '', 'type' => '', 'description' => ''], $create = true)
    {
        try {
            if ($user->notification_token) {
                $this->createNotification($user->notification_token, [
                     'title' => $payload['title'],
                     'body' => strip_tags($payload['description']),
                 ]);
            }

            if ($create) {
                $user->notifications()->create([
                    'title' => $payload['title'],
                    'description' => $payload['description'],
                    'type' => $payload['type'],
                ]);
            }

        } catch (Exception $e) {
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
        if (empty($tokens)) {
            return;
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'access_token_auth' => 'true',
                ])
                ->post('https://iid.googleapis.com/iid/v1:batchAdd', [
                    'to' => "/topics/{$topic}",
                    'registration_tokens' => $tokens,
                ]);

            return $response->json();
        } catch (Exception $e) {
            logger()->error('FCM Topic subscription error: '.$e->getMessage());
        }
    }

    /**
     * Unsubscribe multiple tokens from a topic
     */
    public function unsubscribeBatchFromTopic(array $tokens, string $topic)
    {
        if (empty($tokens)) {
            return;
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'access_token_auth' => 'true',
                ])
                ->post('https://iid.googleapis.com/iid/v1:batchRemove', [
                    'to' => "/topics/{$topic}",
                    'registration_tokens' => $tokens,
                ]);

            return $response->json();
        } catch (Exception $e) {
            logger()->error('FCM Topic unsubscription error: '.$e->getMessage());
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
            $projectId = $this->getProjectId();
            $this->http->withToken($this->getAccessToken())->post($projectId.'/messages:send', [
                'message' => [
                    'condition' => $condition,
                    'notification' => [
                        'title' => $payload['title'],
                        'body' => strip_tags($payload['description'] ?? $payload['body'] ?? ''),
                    ],
                    'data' => $payload['data'] ?? [],
                ],
            ]);
        } catch (Exception $e) {
            logger()->error('FCM Condition send error: '.$e->getMessage());
        }
    }
}
