<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Http\Services\FirebaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGlobalAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $announcement;

    /**
     * Create a new job instance.
     */
    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseNotification $firebase): void
    {
        // Chunk users who have a notification token
        User::whereNotNull('notification_token')
            ->chunk(500, function ($users) use ($firebase) {
                // Prepare the payload for FirebaseNotification
                $payload = [
                    'title' => $this->announcement->title,
                    'description' => $this->announcement->content,
                    'type' => 'announcement'
                ];
                
                // Batch send the push notification
                try {
                    $firebase->pushNotificationBatch($users->all(), $payload);
                } catch (\Exception $e) {
                    \Log::error("Failed to send batch announcement: " . $e->getMessage());
                }
            });
    }
}
