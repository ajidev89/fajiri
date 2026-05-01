<?php

namespace Database\Seeders;
 
use App\Enums\User\AccountType;
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
                'name' => 'Bronze',
                'level' => 'bronze',
                'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
                'slug' => 'bronze',
                'description' => 'Perfect for getting started.',
                'price' => 5000.00,
                'currency' => 'NGN',
                'duration' => 30,
                'features' => [
                    "Support and recognition for birthdays and special occasions",
                    "Access to basic healthcare, life insurance, and wellness support programs"
                ],
                'status' => true,
            ],
            [
                'name' => 'Silver',
                'level' => 'silver',
                'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
                'slug' => 'silver',
                'description' => 'For small brands and creators.',
                'price' => 10000.00,
                'currency' => 'NGN',
                'duration' => 30,
                'features' => ['Standard Support', '5 Campaigns', 'Standard Analytics'],
                'status' => true,
            ],
            [
                'name' => 'Gold',
                'level' => 'gold',
                'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
                'slug' => 'gold',
                'description' => 'Everything you need to grow.',
                'price' => 25000.00,
                'currency' => 'NGN',
                'duration' => 30,
                'features' => ['Priority Support', 'Unlimited Campaigns', 'Advanced Analytics'],
                'status' => true,
            ],
            [
                'name' => 'Platinum',
                'level' => 'platinum',
                'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
                'slug' => 'platinum',
                'description' => 'Custom solutions for your business.',
                'price' => 50000.00,
                'currency' => 'NGN',
                'duration' => 30,
                'features' => ['24/7 Support', 'Custom Integrations', 'Dedicated Account Manager'],
                'status' => true,
            ],
        ];

        foreach ($plans as $plan) {
            \App\Models\Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
