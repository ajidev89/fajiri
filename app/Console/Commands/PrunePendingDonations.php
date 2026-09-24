<?php

namespace App\Console\Commands;

use App\Models\Donation;
use Illuminate\Console\Command;

class PrunePendingDonations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'donations:prune-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pending donations that are older than 24 hours.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = Donation::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subHours(24))
            ->delete();

        $this->info("Removed {$deleted} pending donation(s) older than 24 hours.");

        return self::SUCCESS;
    }
}
