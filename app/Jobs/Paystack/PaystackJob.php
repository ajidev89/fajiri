<?php

namespace App\Jobs\Paystack;

use App\Models\Donation;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Plan;
use App\Http\Traits\PlanActivationTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaystackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PlanActivationTrait;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $event)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $eventType = $this->event['event'] ?? '';

        switch ($eventType) {
            case 'charge.success':
                $this->handleChargeSuccess();
                break;
            case 'subscription.create':
                if (isset($this->event['data']['plan'])) {
                    $this->handleSubscriptionActive($this->event['data']);
                }
                break;
            case 'subscription.disable':
                $this->handleSubscriptionCancelled($this->event['data']);
                break;
        }
    }

    protected function handleChargeSuccess()
    {
        $data = $this->event['data'];
        
        // If it has a plan, it's likely a subscription payment
        if (isset($data['plan']) && !empty($data['plan'])) {
            $this->handleSubscriptionActive($data);
            return;
        }

        $reference = $data['reference'];
        $type = $data['metadata']['type'] ?? 'wallet_funding';

        if ($type === 'wallet_funding') {
            $this->processWalletFunding($data, $reference);
        } elseif (in_array($type, ['campaign_donation', 'need_donation', 'donation'])) {
            $this->processDonation($data, $reference, $type);
        } elseif ($type === 'event_attendance') {
            $this->processEventAttendance($data, $reference);
        }
    }

    protected function handleSubscriptionActive($data)
    {
        $userId = $data['metadata']['user_id'] ?? null;
        $planId = $data['metadata']['plan_id'] ?? null;
        $subscriptionCode = $data['subscription_code'] ?? null;

        if ($userId && $planId) {
            $user = User::find($userId);
            $plan = Plan::find($planId);

            if ($user && $plan) {
                $this->activateUserPlan($user, $plan, 'paystack', $subscriptionCode);
            }
        }
    }

    protected function handleSubscriptionCancelled($data)
    {
        $subscriptionCode = $data['subscription_code'] ?? null;
        if ($subscriptionCode) {
            $this->deactivateUserPlanBySubscriptionId('paystack', $subscriptionCode);
        }
    }

    protected function processWalletFunding($data, $reference)
    {
        // Check if already processed to avoid double crediting
        $transactionExists = Transaction::where('reference', $reference)->exists();

        if (!$transactionExists) {
            $userId = $data['metadata']['user_id'];
            $amount = $data['amount'] / 100;

            $user = User::find($userId);
            if ($user) {
                $user->deposit($amount, "Wallet funding via Paystack", $reference);

                // Create notification
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Wallet Funded',
                    'message' => "Your wallet has been credited with {$user->wallet->currency} " . number_format($amount, 2) . " via Paystack.",
                    'type' => 'wallet_funding',
                    'data' => [
                        'amount' => $amount,
                        'reference' => $reference,
                        'currency' => $user->wallet->currency
                    ]
                ]);
            }
        }
    }

    protected function processDonation($data, $reference, $type)
    {
        $donation = Donation::where('reference', $reference)->first();
        if ($donation && $donation->status === 'pending') {
            $donation->update(['status' => 'completed']);

            // Notify donor if they are a registered user
            if ($donation->user_id) {
                $title = $donation->donatable_type === \App\Models\Campaign::class 
                    ? $donation->donatable->title 
                    : $donation->donatable->name;
                    
                \App\Models\Notification::create([
                    'user_id' => $donation->user_id,
                    'title' => 'Donation Successful',
                    'message' => "Your donation of {$donation->currency} " . number_format($donation->amount, 2) . " to '{$title}' was successful.",
                    'type' => $type,
                    'data' => [
                        'donation_id' => $donation->id,
                        'donatable_id' => $donation->donatable_id,
                        'donatable_type' => $donation->donatable_type,
                        'amount' => $donation->amount,
                        'currency' => $donation->currency
                    ]
                ]);
            }
        }
    }

    protected function processEventAttendance($data, $reference)
    {
        $attendee = \App\Models\EventAttendee::where('code', $reference)->first();
        if ($attendee && $attendee->status === \App\Enums\Attendee\Status::INACTIVE->value) {
            $attendee->update(['status' => \App\Enums\Attendee\Status::ACTIVE->value]);
            
            // Notify user if registered
            if ($attendee->user_id) {
                \App\Models\Notification::create([
                    'user_id' => $attendee->user_id,
                    'title' => 'Event Registration Successful',
                    'message' => "Your payment for the event '{$attendee->event->title}' was successful. Your code is: {$attendee->code}",
                    'type' => 'event_registration',
                    'data' => [
                        'event_id' => $attendee->event_id,
                        'attendee_code' => $attendee->code
                    ]
                ]);
            }
        }
    }
}
