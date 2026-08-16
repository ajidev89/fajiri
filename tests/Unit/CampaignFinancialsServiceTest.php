<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Donation;
use App\Services\CampaignFinancialsService;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignFinancialsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_financials_calculation_with_donations(): void
    {
        $currencyService = app(CurrencyService::class);
        $service = new CampaignFinancialsService($currencyService);

        \App\Models\Country::create([
            'id'         => 1,
            'name'       => 'Nigeria',
            'iso3'       => 'NGA',
            'iso2'       => 'NG',
            'currency'   => 'NGN',
            'phone_code' => '+234',
        ]);

        $role = \App\Models\Role::create(['id' => 1, 'name' => 'User', 'slug' => 'user']);

        $user = \App\Models\User::create([
            'email'             => 'owner@example.com',
            'phone'             => '+2348011223344',
            'password'          => \Illuminate\Support\Facades\Hash::make('secret123'),
            'role_id'           => 1,
            'country_id'        => 1,
            'account_type'      => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'member_id'         => 'FAJ-TEST-OWNER-F',
        ]);

        $campaign = Campaign::create([
            'added_by'    => $user->id,
            'title'       => 'Medical Emergency Campaign',
            'body'        => 'Helping patient with urgent treatment',
            'goal_amount' => 50000.0,
            'currency'    => 'USD',
            'status'      => 'active',
            'type'        => 'medical-aid',
        ]);

        // Create 2 completed donations
        Donation::create([
            'donatable_type'   => Campaign::class,
            'donatable_id'     => $campaign->id,
            'amount'           => 10000.0,
            'converted_amount' => 10000.0,
            'rate'             => 1.0,
            'currency'         => 'USD',
            'fee'              => 250.0,
            'status'           => 'completed',
        ]);

        Donation::create([
            'donatable_type'   => Campaign::class,
            'donatable_id'     => $campaign->id,
            'amount'           => 5000.0,
            'converted_amount' => 5000.0,
            'rate'             => 1.0,
            'currency'         => 'USD',
            'fee'              => 125.0,
            'status'           => 'completed',
        ]);

        $financials = $service->getCampaignFinancials($campaign);

        $this->assertEquals(15000.0, $financials['total_raised']);
        $this->assertEquals(375.0, $financials['platform_fees']);
        $this->assertEquals(14625.0, $financials['available_funds']);
        $this->assertEquals(14625.0, $financials['available_balance']);
        $this->assertEquals(0, $financials['disbursements_count']);
    }

    public function test_fee_calculation_engine(): void
    {
        $currencyService = app(CurrencyService::class);
        $service = new CampaignFinancialsService($currencyService);

        // 1. Campaign bears fee
        $result1 = $service->calculateFee(5000.0, 'local_bank_transfer', 'campaign');
        $this->assertEquals(5000.0, $result1['requested_amount']);
        $this->assertEquals(5000.0, $result1['recipient_receives']);
        $this->assertEquals(25.50, $result1['fee_amount']); // 5000 * 0.005 + 0.50
        $this->assertEquals(5025.50, $result1['total_deducted']);

        // 2. Recipient bears fee
        $result2 = $service->calculateFee(5000.0, 'local_bank_transfer', 'recipient');
        $this->assertEquals(5000.0, $result2['requested_amount']);
        $this->assertEquals(4974.50, $result2['recipient_receives']);
        $this->assertEquals(5000.0, $result2['total_deducted']);
    }
}
