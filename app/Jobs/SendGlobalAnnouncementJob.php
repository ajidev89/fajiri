<?php

namespace App\Jobs;

use App\Enums\User\AccountType;
use App\Http\Services\FirebaseNotification;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendGlobalAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Run once: a retry would create duplicate in-app notifications for users
     * who were already processed before the failure.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Announcement $announcement) {}

    public function handle(FirebaseNotification $firebase): void
    {
        $this->targetedUsers()->chunkById(500, function ($users) use ($firebase) {
            // Always record the in-app notification, even if the device push fails.
            $this->createInAppNotifications($users);

            try {
                $firebase->pushNotificationBatch($users->all(), [
                    'title' => $this->announcement->title,
                    'description' => $this->announcement->content,
                    'type' => 'announcement',
                    'image' => $this->announcement->image_url,
                    'data' => [
                        'announcement_id' => $this->announcement->id,
                    ],
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send batch announcement push: '.$e->getMessage(), [
                    'announcement_id' => $this->announcement->id,
                ]);
            }
        });
    }

    private function targetedUsers(): Builder
    {
        $targetAudience = $this->announcement->target_audience ?? [];
        $query = User::query();

        if (empty($targetAudience) || in_array('all', $targetAudience)) {
            return $query;
        }

        return $query->where(function ($q) use ($targetAudience) {
            $roles = array_intersect($targetAudience, [
                'admin', 'user', 'fundraiser', 'membership-manager',
                'donation-manager', 'campaign-manager', 'poll-manager',
                'financial-officer', 'system-administrator',
            ]);

            if (! empty($roles)) {
                $q->orWhereHas('role', function ($r) use ($roles) {
                    $r->whereIn('slug', $roles);
                });
            }

            $accountTypes = [];
            if (in_array('fim', $targetAudience)) {
                $accountTypes[] = AccountType::IDENTIFIED_MEMBERSHIP->value;
            }
            if (in_array('fpm', $targetAudience)) {
                $accountTypes[] = AccountType::PROGRAM_MEMBERSHIP->value;
            }
            if (in_array('fcm', $targetAudience)) {
                $accountTypes[] = AccountType::CORPORATE_MEMBERSHIP->value;
            }

            if (! empty($accountTypes)) {
                $q->orWhereIn('account_type', $accountTypes);
            }

            if (in_array('active_users', $targetAudience)) {
                $q->orWhere('status', 'active');
            }
            if (in_array('non_active_users', $targetAudience)) {
                $q->orWhere('status', '!=', 'active');
            }
        });
    }

    private function createInAppNotifications(Collection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $now = now();

        $records = $users->map(fn (User $user) => [
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'title' => $this->announcement->title,
            'message' => $this->announcement->content,
            'type' => 'announcement',
            'data' => json_encode([
                'announcement_id' => $this->announcement->id,
                'image_url' => $this->announcement->image_url,
            ]),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Notification::insert($records);
    }
}
