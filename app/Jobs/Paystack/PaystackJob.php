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
                    }
                }
            } elseif ($type === 'campaign_donation') {
                $donation = Donation::where('reference', $reference)->first();
                if ($donation && $donation->status === 'pending') {
                    $donation->update(['status' => 'completed']);
                }
            }
        }
    }
}
