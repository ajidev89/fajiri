<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'user_management',
                'description' => 'Define and manage access levels, edit, suspend, unsuspend, reactivate, or delete administrative and standard users.'
            ],
            [
                'name' => 'membership_management',
                'description' => 'Manage membership levels, subscription plans, pricing, benefits, and user subscriptions.'
            ],
            [
                'name' => 'donation_management',
                'description' => 'Track donations, process refunds, and configure donation policies.'
            ],
            [
                'name' => 'campaign_management',
                'description' => 'Create, edit, approve, suspend, or delete campaigns, initiatives, and needs.'
            ],
            [
                'name' => 'poll_management',
                'description' => 'Create and moderate polls, vote counts, and poll parameters.'
            ],
            [
                'name' => 'reports_analytics',
                'description' => 'View performance metrics, donation charts, top-performing campaigns, and system audits.'
            ],
            [
                'name' => 'financial_records',
                'description' => 'View transactions, disburse funds, manage withdrawal accounts, and reject disbursement requests.'
            ],
            [
                'name' => 'system_settings',
                'description' => 'Manage general system configuration, country listing, preferences, categories, posts, and partner listings.'
            ]
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }
    }
}
