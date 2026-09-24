<?php

namespace App\Console\Commands;

use App\Enums\User\Status;
use App\Http\Services\FirebaseNotification;
use App\Mail\MembershipUnpaidReminderMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindUnpaidMemberships extends Command
{
    private const REMINDER_INTERVAL_DAYS = 7;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:remind-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind people who have never paid for a membership to subscribe, at most once a week.';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseNotification $firebase): int
    {
        $reminded = 0;

        $this->neverPaidMembers()->chunkById(200, function ($users) use ($firebase, &$reminded) {
            $notified = collect();

            foreach ($users as $user) {
                if (! filled($user->email)) {
                    continue;
                }

                $this->createReminder($user);
                Mail::to($user->email)->queue(new MembershipUnpaidReminderMail($user));
                $notified->push($user);
                $reminded++;
            }

            if ($notified->isEmpty()) {
                return;
            }

            try {
                $firebase->pushNotificationBatch($notified->all(), [
                    'title' => 'Subscribe to a membership',
                    'description' => 'You have not paid for a membership yet. Subscribe to a plan to get started.',
                    'type' => 'membership_subscribe_reminder',
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send membership subscribe push reminders: '.$e->getMessage());
            }
        });

        $this->info("Sent {$reminded} subscribe reminder(s).");

        return self::SUCCESS;
    }

    private function neverPaidMembers(): Builder
    {
        return User::query()
            ->where('status', Status::ACTIVE->value)
            ->where(function (Builder $query) {
                $query->whereDoesntHave('preference')
                    ->orWhereHas('preference', function (Builder $preference) {
                        $preference->where('membership_status_updates', true);
                    });
            })
            ->whereDoesntHave('plans', function (Builder $query) {
                $query->where('plans.price', '>', 0);
            })
            ->whereDoesntHave('notifications', function (Builder $query) {
                $query->where('type', 'membership_subscribe_reminder')
                    ->where('created_at', '>', now()->subDays(self::REMINDER_INTERVAL_DAYS));
            })
            ->with('profile');
    }

    private function createReminder(User $user): void
    {
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Subscribe to a membership',
            'message' => 'You have not paid for a membership yet. Subscribe to a plan to get started.',
            'type' => 'membership_subscribe_reminder',
        ]);
    }
}
