<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\User;
use App\Http\Services\FirebaseNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Enums\User\AccountType;

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
        $targetAudience = $this->announcement->target_audience ?? [];

        $query = User::whereNotNull('notification_token');

        if (!empty($targetAudience) && !in_array('all', $targetAudience)) {
            $query->where(function ($q) use ($targetAudience) {
                // Check for roles
                $roles = array_intersect($targetAudience, [
                    'admin', 'user', 'fundraiser', 'membership-manager', 
                    'donation-manager', 'campaign-manager', 'poll-manager', 
                    'financial-officer', 'system-administrator'
                ]);
                
                if (!empty($roles)) {
                    $q->orWhereHas('role', function ($r) use ($roles) {
                        $r->whereIn('slug', $roles);
                    });
                }

                // Check for Account Types
                $accountTypes = [];
                if (in_array('fim', $targetAudience)) $accountTypes[] = AccountType::IDENTIFIED_MEMBERSHIP->value;
                if (in_array('fpm', $targetAudience)) $accountTypes[] = AccountType::PROGRAM_MEMBERSHIP->value;
                if (in_array('fcm', $targetAudience)) $accountTypes[] = AccountType::CORPORATE_MEMBERSHIP->value;
                
                if (!empty($accountTypes)) {
                    $q->orWhereIn('account_type', $accountTypes);
                }

                // Check for Status
                if (in_array('active_users', $targetAudience)) {
                    $q->orWhere('status', 'active');
                }
                if (in_array('non_active_users', $targetAudience)) {
                    $q->orWhere('status', '!=', 'active');
                }
            });
        }

        $query->chunk(500, function ($users) use ($firebase) {
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
