<?php

namespace Tests\Feature;

use App\Enums\Disbursement\Status;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\Disbursement;
use App\Models\Donation;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DisbursementAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        Country::create([
            'id' => 1,
            'name' => 'Nigeria',
            'iso3' => 'NGA',
            'iso2' => 'NG',
            'currency' => 'NGN',
            'phone_code' => '+234',
        ]);

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AddAdminAccount::class);

        $this->admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->first();

        $this->campaign = Campaign::create([
            'added_by' => $this->admin->id,
            'title' => 'Relief Campaign',
            'body' => 'Support families in need',
            'goal_amount' => 50000,
            'currency' => 'USD',
            'status' => 'active',
            'type' => 'other',
        ]);

        Http::fake([
            '*' => Http::response([
                'conversion_rate' => 0.00067,
                'rates' => ['USD' => 0.00067, 'NGN' => 1],
            ], 200),
        ]);
    }

    public function test_available_funds_usd_uses_donation_usd_totals_not_ngn_roundtrip(): void
    {
        Donation::create([
            'donatable_type' => Campaign::class,
            'donatable_id' => $this->campaign->id,
            'amount' => 10000,
            'converted_amount' => 10000,
            'base_amount_usd' => 10000,
            'rate' => 1,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        Disbursement::create([
            'disbursable_type' => Campaign::class,
            'disbursable_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
            'beneficiary_name' => 'Jane Doe',
            'payment_method' => 'ach',
            'account_name' => 'Jane Doe',
            'account_number' => '1234567890',
            'bank_name' => 'Chase',
            'amount' => 1500,
            'currency' => 'USD',
            'converted_amount' => 1500,
            'status' => Status::COMPLETED,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/analytics/disbursements')
            ->assertOk()
            ->assertJsonPath('data.available_funds_usd', 8500);
    }

    public function test_available_funds_usd_subtracts_only_completed_disbursements(): void
    {
        Donation::create([
            'donatable_type' => Campaign::class,
            'donatable_id' => $this->campaign->id,
            'amount' => 5000,
            'converted_amount' => 5000,
            'base_amount_usd' => 5000,
            'rate' => 1,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        Disbursement::create([
            'disbursable_type' => Campaign::class,
            'disbursable_id' => $this->campaign->id,
            'requested_by' => $this->admin->id,
            'beneficiary_name' => 'Pending Recipient',
            'payment_method' => 'ach',
            'account_name' => 'Pending Recipient',
            'account_number' => '1111111111',
            'bank_name' => 'Chase',
            'amount' => 2000,
            'currency' => 'USD',
            'converted_amount' => 2000,
            'status' => Status::PENDING,
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/analytics/disbursements')
            ->assertOk()
            ->assertJsonPath('data.available_funds_usd', 5000)
            ->assertJsonPath('data.pending_disbursements.amounts.USD', 2000);
    }
}
