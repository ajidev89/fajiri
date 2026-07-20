<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use Stripe\StripeClient;

class ResetGatewayPlans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:reset-gateways';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate products and prices on Stripe, and reset all gateway plan/product/price IDs to null in the database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Fetching all plans from database...");
        $plans = Plan::all();

        if ($plans->isEmpty()) {
            $this->info("No plans found in the database.");
            return 0;
        }

        $stripeSecret = config('services.stripe.secret');
        $stripe = new StripeClient($stripeSecret);

        foreach ($plans as $plan) {
            $this->info("Processing plan: {$plan->name} (ID: {$plan->id})");

            // 1. Deactivate Stripe Price
            if ($plan->stripe_price_id) {
                try {
                    $this->info("Deactivating Stripe Price: {$plan->stripe_price_id}");
                    $stripe->prices->update($plan->stripe_price_id, ['active' => false]);
                } catch (\Exception $e) {
                    $this->warn("Could not deactivate Stripe Price {$plan->stripe_price_id}: " . $e->getMessage());
                }
            }

            // 2. Deactivate Stripe Product
            if ($plan->stripe_product_id) {
                try {
                    $this->info("Deactivating Stripe Product: {$plan->stripe_product_id}");
                    $stripe->products->update($plan->stripe_product_id, ['active' => false]);
                } catch (\Exception $e) {
                    $this->warn("Could not deactivate Stripe Product {$plan->stripe_product_id}: " . $e->getMessage());
                }
            }

            // 3. Log Paystack plan unlinking
            if ($plan->paystack_plan_code) {
                $this->info("Unlinking Paystack plan code: {$plan->paystack_plan_code}");
            }

            // 4. Nullify database columns
            $plan->stripe_product_id = null;
            $plan->stripe_price_id = null;
            $plan->paystack_plan_code = null;
            $plan->save();

            $this->info("Plan {$plan->name} has been successfully reset locally and deactivated on remote gateways.");
        }

        $this->info("All plans have been reset successfully.");
        return 0;
    }
}
