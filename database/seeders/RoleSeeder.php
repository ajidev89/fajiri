<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolesData = [
            'Super Admin' => [
                'user_management',
                'membership_management',
                'donation_management',
                'campaign_management',
                'poll_management',
                'reports_analytics',
                'financial_records',
                'system_settings'
            ],
            'Admin' => [
                'membership_management',
                'donation_management',
                'campaign_management',
                'poll_management',
                'reports_analytics'
            ],
            'User' => [],
            'Fundraiser' => [],
            'Membership Manager' => ['membership_management', 'reports_analytics'],
            'Donation Manager' => ['donation_management', 'reports_analytics'],
            'Campaign Manager' => ['campaign_management', 'reports_analytics'],
            'Poll Manager' => ['poll_management'],
            'Financial Officer' => ['financial_records', 'reports_analytics'],
            'System Administrator' => ['system_settings', 'user_management']
        ];

        foreach ($rolesData as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                $role = Role::create([
                    'name' => $roleName
                ]);
            }

            $permissionIds = Permission::whereIn('name', $perms)->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);
        }
    }
}
