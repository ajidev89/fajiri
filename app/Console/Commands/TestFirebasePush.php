<?php

namespace App\Console\Commands;

use App\Http\Services\FirebaseNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class TestFirebasePush extends Command
{
    protected $signature = 'fcm:test
        {--email= : Send a real push to this user\'s registered device}
        {--token= : Send a real push to this raw FCM device token}
        {--title=Fajiri test notification : Notification title}
        {--body=If you can read this, push notifications work. : Notification body}';

    protected $description = 'Check Firebase push notifications (validate-only by default, or a real send with --email/--token)';

    public function handle(FirebaseNotification $firebase): int
    {
        // 1. Credentials + project
        try {
            $projectId = $firebase->getProjectId();
            $this->info("✔ Service account loaded (project: {$projectId})");
        } catch (Throwable $e) {
            $this->error('✘ Service account: '.$e->getMessage());
            return self::FAILURE;
        }

        // 2. OAuth access token
        try {
            $accessToken = $firebase->getAccessToken();
            $this->info('✔ Google access token obtained');
        } catch (Throwable $e) {
            $this->error('✘ Access token: '.$e->getMessage());
            return self::FAILURE;
        }

        $payload = [
            'title' => $this->option('title'),
            'description' => $this->option('body'),
            'type' => 'test',
            'data' => ['source' => 'fcm:test'],
        ];

        // 3a. Real send to a user or raw token, through the same code path announcements use
        if ($this->option('email') || $this->option('token')) {
            if ($email = $this->option('email')) {
                $user = User::where('email', $email)->first();
                if (! $user) {
                    $this->error("✘ No user with email {$email}");
                    return self::FAILURE;
                }
                if (! $user->notification_token) {
                    $this->error("✘ {$email} has no notification_token (they need to log in on the app to register a device)");
                    return self::FAILURE;
                }
            } else {
                $user = new User(['notification_token' => $this->option('token')]);
            }

            $result = $firebase->pushNotificationBatch([$user], $payload);
            $this->line('Result: '.json_encode($result));

            if ($result['sent'] === 1) {
                $this->info('✔ Push accepted by Firebase — check the device.');
                return self::SUCCESS;
            }
            if ($result['invalid'] === 1) {
                $this->error('✘ Firebase says this device token is no longer registered (app uninstalled or token rotated). It has been cleared.');
                return self::FAILURE;
            }
            $this->error('✘ Push failed — see storage/logs/laravel.log for the Firebase error.');
            return self::FAILURE;
        }

        // 3b. Default: validate-only send (nothing is delivered)
        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'validate_only' => true,
                'message' => [
                    'topic' => 'fajiri-fcm-test',
                    'notification' => ['title' => $payload['title'], 'body' => $payload['description']],
                    'data' => ['type' => 'test'],
                ],
            ]);

        if ($response->successful()) {
            $this->info('✔ Firebase accepted a validate-only message — credentials, project and message format are OK.');
            $withTokens = User::whereNotNull('notification_token')->count();
            $this->line("Users with a registered device: {$withTokens}");
            $this->line('Run with --email=someone@example.com to send a real push to a device.');
            return self::SUCCESS;
        }

        $this->error("✘ Firebase rejected the message ({$response->status()}): ".($response->json('error.message') ?? $response->body()));
        return self::FAILURE;
    }
}
