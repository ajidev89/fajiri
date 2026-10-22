<?php

namespace App\Jobs\Paystack;

use App\Enums\Attendee\Status;
use App\Http\Traits\PlanActivationTrait;
use App\Mail\DepositSuccessMail;
use App\Mail\SubscriptionSuccessMail;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\EventAttendee;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionAttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class PaystackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PlanActivationTrait, Queueable, SerializesModels;

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
    public function handle(SubscriptionAttemptService $attempts): void
    {
        $eventType = $this->event['event'] ?? '';

        switch ($eventType) {
            case 'charge.success':
                $this->handleChargeSuccess($attempts);
                break;
            case 'subscription.disable':
                $this->handleSubscriptionCancelled($this->event['data']);
                break;
        }
    }

    protected function handleChargeSuccess(SubscriptionAttemptService $attempts): void
    {
        $data = $this->event['data'];

        if (isset($data['plan']) && ! empty($data['plan'])) {
            $this->confirmSubscriptionCharge($data, $attempts);

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

    /**
     * @param  array<string, mixed>  $data
     */
    protected function confirmSubscriptionCharge(array $data, SubscriptionAttemptService $attempts): void
    {
        $reference = $data['reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return;
        }

        $confirmed = $attempts->confirm(
            'paystack',
            $reference,
            ((float) ($data['amount'] ?? 0)) / 100,
            isset($data['currency']) ? (string) $data['currency'] : 'NGN',
            isset($data['subscription_code']) ? (string) $data['subscription_code'] : null,
        );

        if ($confirmed === null) {
            return;
        }

        try {
            Mail::to($confirmed['user']->email)->send(new SubscriptionSuccessMail(
                $confirmed['user'],
                $confirmed['plan'],
                $confirmed['amount'],
                $confirmed['currency'],
            ));
        } catch (\Exception $e) {
            \Log::error('Failed to send paystack subscription email: '.$e->getMessage());
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

        if (! $transactionExists) {
            $userId = $data['metadata']['user_id'];
            $amount = $data['amount'] / 100;

            $user = User::find($userId);
            if ($user) {
                $user->deposit($amount, 'Wallet funding via Paystack', $reference);

                // Create notification
                Notification::create([
                    'user_id' => $user->id,
                    'title' => 'Wallet Funded',
                    'message' => "Your wallet has been credited with {$user->wallet->currency} ".number_format($amount, 2).' via Paystack.',
                    'type' => 'wallet_funding',
                    'data' => [
                        'amount' => $amount,
                        'reference' => $reference,
                        'currency' => $user->wallet->currency,
                    ],
                ]);

                // Send Email
                try {
                    Mail::to($user->email)->send(new DepositSuccessMail($user, $amount, $user->wallet->currency, $reference));
                } catch (\Exception $e) {
                    \Log::error('Failed to send paystack deposit email: '.$e->getMessage());
                }
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
                $title = $donation->donatable_type === Campaign::class
                    ? $donation->donatable->title
                    : $donation->donatable->name;

                Notification::create([
                    'user_id' => $donation->user_id,
                    'title' => 'Donation Successful',
                    'message' => "Your donation of {$donation->currency} ".number_format($donation->amount, 2)." to '{$title}' was successful.",
                    'type' => $type,
                    'data' => [
                        'donation_id' => $donation->id,
                        'donatable_id' => $donation->donatable_id,
                        'donatable_type' => $donation->donatable_type,
                        'amount' => $donation->amount,
                        'currency' => $donation->currency,
                    ],
                ]);
            }
        }
    }

    protected function processEventAttendance($data, $reference)
    {
        $attendee = EventAttendee::where('code', $reference)->first();
        if ($attendee && $attendee->status === Status::INACTIVE->value) {
            $attendee->update(['status' => Status::ACTIVE->value]);

            // Notify user if registered
            if ($attendee->user_id) {
                Notification::create([
                    'user_id' => $attendee->user_id,
                    'title' => 'Event Registration Successful',
                    'message' => "Your payment for the event '{$attendee->event->title}' was successful. Your code is: {$attendee->code}",
                    'type' => 'event_registration',
                    'data' => [
                        'event_id' => $attendee->event_id,
                        'attendee_code' => $attendee->code,
                    ],
                ]);
            }
        }
    }
}
