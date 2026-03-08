<?php

namespace App\Console\Commands;

use App\Mail\RenewalMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RenewSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically renew expiring subscriptions and send email notifications.';

    /**
     * Execute the console command.
     */
    public function handle(
        \App\Http\Repository\Contracts\PlanRepositoryInterface $planRepository,
    ) {
        $this->info("Starting subscription renewal process...");

        // Find active subscriptions expiring soon (e.g., in the next 24 hours) or already expired but within a grace period
        $expiringSubscriptions = DB::table('user_plans')
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->where('expires_at', '<=', now()->addDay())
            ->get();

        foreach ($expiringSubscriptions as $userPlan) {
            $user = \App\Models\User::find($userPlan->user_id);
            $plan = \App\Models\Plan::find($userPlan->plan_id);

            $this->info("Attempting to renew plan '{$plan->name}' for user '{$user->email}'...");

            try {
                $planRepository->renewSubscription($userPlan->id);

                $this->info("Successfully renewed plan '{$plan->name}' for user '{$user->email}'.");

                // Send Success email
                Mail::to($user->email)->send(
                    new RenewalMail($user, $plan, 'success')
                );

            } catch (\Exception $e) {
                $this->error("Failed to renew plan '{$plan->name}' for user '{$user->email}': " . $e->getMessage());

                // Send Failure email
                Mail::to($user->email)->send(
                    new RenewalMail($user, $plan, 'failed', $e->getMessage())
                );
            }
        }

        $this->info("Subscription renewal process completed.");
    }
}
