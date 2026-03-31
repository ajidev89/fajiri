<?php

namespace App\Jobs\Paystack;

use App\Models\Donation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaystackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        if ($this->event['event'] === 'charge.success') {
            $data = $this->event['data'];
            $reference = $data['reference'];
            $type = $data['metadata']['type'] ?? 'wallet_funding';

            if ($type === 'wallet_funding') {
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
            } elseif (in_array($type, ['campaign_donation', 'need_donation'])) {
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
        }
    }
}
