<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started.',
                'price' => 0.00,
                'currency' => 'USD',
                'duration' => 30,
                'features' => json_encode(['Basic Support', '1 Campaign', 'Limited Analytics']),
                'status' => true,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For small brands and creators.',
                'price' => 19.99,
                'currency' => 'USD',
                'duration' => 30,
                'features' => json_encode(['Standard Support', '5 Campaigns', 'Standard Analytics']),
                'status' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Everything you need to grow.',
                'price' => 49.99,
                'currency' => 'USD',
                'duration' => 30,
                'features' => json_encode(['Priority Support', 'Unlimited Campaigns', 'Advanced Analytics']),
                'status' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for your business.',
                'price' => 199.99,
                'currency' => 'USD',
                'duration' => 30,
                'features' => json_encode(['24/7 Support', 'Custom Integrations', 'Dedicated Account Manager']),
                'status' => true,
            ],
        ];

        foreach ($plans as $plan) {
            \App\Models\Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
