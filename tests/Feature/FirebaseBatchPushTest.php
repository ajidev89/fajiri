<?php

namespace Tests\Feature;

use App\Http\Services\FirebaseNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class FirebaseBatchPushTest extends TestCase
{
    use RefreshDatabase;

    private function firebase(): FirebaseNotification
    {
        $firebase = Mockery::mock(FirebaseNotification::class)->makePartial();
        $firebase->shouldReceive('getAccessToken')->andReturn('test-access-token');
        $firebase->shouldReceive('getProjectId')->andReturn('fajiri-test');

        return $firebase;
    }

    public function test_sends_one_fcm_v1_message_per_device_token(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/fajiri-test/messages/1'])]);

        $users = [
            new User(['notification_token' => 'token-a']),
            new User(['notification_token' => 'token-b']),
            new User(['notification_token' => 'token-a']), // duplicate device
            new User(['notification_token' => null]),       // no device
        ];

        $result = $this->firebase()->pushNotificationBatch($users, [
            'title' => 'Community Update',
            'description' => '<p>New programs are live.</p>',
            'type' => 'announcement',
            'data' => ['announcement_id' => 'abc-123'],
        ]);

        $this->assertSame(['sent' => 2, 'failed' => 0, 'invalid' => 0], $result);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            $message = $request['message'];

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/fajiri-test/messages:send'
                && $request->hasHeader('Authorization', 'Bearer test-access-token')
                && in_array($message['token'], ['token-a', 'token-b'], true)
                && ! isset($message['tokens'])
                && $message['notification']['title'] === 'Community Update'
                && $message['notification']['body'] === 'New programs are live.'
                && $message['data']['type'] === 'announcement'
                && $message['data']['announcement_id'] === 'abc-123';
        });
    }

    public function test_counts_unregistered_tokens_as_invalid(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 404,
                'status' => 'NOT_FOUND',
                'details' => [['errorCode' => 'UNREGISTERED']],
            ],
        ], 404)]);

        $result = $this->firebase()->pushNotificationBatch(
            [new User(['notification_token' => 'stale-token'])],
            ['title' => 'Hi', 'description' => 'There', 'type' => 'announcement'],
        );

        $this->assertSame(1, $result['invalid']);
        $this->assertSame(0, $result['sent']);
    }
}
