<?php

namespace App\Console\Commands;

use App\Enums\User\Status;
use App\Http\Services\FirebaseNotification;
use App\Mail\MembershipUnpaidReminderMail;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RemindUnpaidMemberships extends Command
{
    private const REMINDER_INTERVAL_DAYS = 3;

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
    protected $description = 'Remind members whose paid membership has lapsed and is still unpaid.';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseNotification $firebase): int
    {
        $reminded = 0;

        $this->unpaidMembers()->chunkById(200, function ($users) use ($firebase, &$reminded) {
            $notified = collect();

            foreach ($users as $user) {
                $plan = $this->lapsedPlan($user);

                if ($plan === null || ! filled($user->email)) {
                    continue;
                }

                $this->createReminder($user, $plan);
                Mail::to($user->email)->queue(new MembershipUnpaidReminderMail($user, $plan));
                $notified->push($user);
                $reminded++;
            }

            if ($notified->isEmpty()) {
                return;
            }

            try {
                $firebase->pushNotificationBatch($notified->all(), [
                    'title' => 'Membership payment due',
                    'description' => 'Your membership is unpaid. Please renew to keep it active.',
                    'type' => 'membership_unpaid_reminder',
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to send unpaid membership push reminders: '.$e->getMessage());
            }
        });

        $this->info("Sent {$reminded} unpaid membership reminder(s).");

        return self::SUCCESS;
    }

    private function unpaidMembers(): Builder
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
                $query->where('user_plans.status', 'active')
                    ->where(function (Builder $coverage) {
                        $coverage->whereNull('user_plans.expires_at')
                            ->orWhere('user_plans.expires_at', '>', now());
                    });
            })
            ->whereHas('plans', function (Builder $query) {
                $query->where('plans.price', '>', 0)
                    ->where('user_plans.expires_at', '<=', now());
            })
            ->whereDoesntHave('notifications', function (Builder $query) {
                $query->where('type', 'membership_unpaid_reminder')
                    ->where('created_at', '>', now()->subDays(self::REMINDER_INTERVAL_DAYS));
            })
            ->with(['profile', 'plans']);
    }

    private function lapsedPlan(User $user): ?Plan
    {
        return $user->plans
            ->filter(function (Plan $plan) {
                $expiresAt = $plan->pivot->expires_at;

                return (float) $plan->price > 0
                    && $expiresAt !== null
                    && Carbon::parse($expiresAt)->lte(now());
            })
            ->sortByDesc(fn (Plan $plan) => Carbon::parse($plan->pivot->expires_at)->timestamp)
            ->first();
    }

    private function createReminder(User $user, Plan $plan): void
    {
        $endedOn = Carbon::parse($plan->pivot->expires_at)->format('F j, Y');

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Membership payment due',
            'message' => "Your {$plan->name} membership is unpaid. It ended on {$endedOn}. Please renew to keep it active.",
            'type' => 'membership_unpaid_reminder',
            'data' => [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'expires_at' => Carbon::parse($plan->pivot->expires_at)->toIso8601String(),
            ],
        ]);
    }
}
