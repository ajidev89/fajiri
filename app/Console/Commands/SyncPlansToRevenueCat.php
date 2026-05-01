<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncPlansToRevenueCat extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-plans-to-revenue-cat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all plans in the database with RevenueCat';

    /**
     * Execute the console command.
     */
    public function handle(\App\Http\Repository\Contracts\PlanRepositoryInterface $planRepository)
    {
        $plans = \App\Models\Plan::all();
        
        if ($plans->isEmpty()) {
            $this->info('No plans found to sync.');
            return;
        }

        $this->info("Syncing {$plans->count()} plans with RevenueCat...");

        $bar = $this->output->createProgressBar($plans->count());

        foreach ($plans as $plan) {
            $planRepository->syncWithRevenueCat($plan);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sync completed successfully.');
    }
}
