<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Role;
use App\Models\User;
use App\Services\CampaignFinancialsService;
use App\Services\CurrencyService;
use App\Services\DisbursementComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DisbursementComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Country::create([
            'id'         => 1,
            'name'       => 'Nigeria',
            'iso3'       => 'NGA',
            'iso2'       => 'NG',
            'currency'   => 'NGN',
            'phone_code' => '+234',
        ]);
    }

    public function test_compliance_evaluation_passes_for_valid_request(): void
    {
        $currencyService = app(CurrencyService::class);
        $financialsService = new CampaignFinancialsService($currencyService);
        $complianceService = new DisbursementComplianceService($financialsService);

        Role::create(['id' => 1, 'name' => 'Member', 'slug' => 'member']);

        $user = User::create([
            'email'             => 'owner@example.com',
            'phone'             => '+2348011223344',
            'password'          => Hash::make('secret123'),
            'role_id'           => 1,
            'country_id'        => 1,
            'account_type'      => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'member_id'         => 'FAJ-TEST-OWNER',
        ]);

        $campaign = Campaign::create([
            'added_by'    => $user->id,
            'title'       => 'Clean Water Initiative',
            'body'        => 'Providing clean water',
            'goal_amount' => 20000.0,
            'currency'    => 'USD',
            'status'      => 'active',
            'type'        => 'water',
        ]);

        Donation::create([
            'donatable_type'   => Campaign::class,
            'donatable_id'     => $campaign->id,
            'amount'           => 10000.0,
            'converted_amount' => 10000.0,
            'rate'             => 1.0,
            'currency'         => 'USD',
            'status'           => 'completed',
        ]);

        $eval = $complianceService->evaluateCompliance($campaign, $user, [
            'beneficiary_name'    => 'Water Project Organization',
            'recipient_country'   => 'US',
            'amount'              => 3000.0,
            'payout_method'       => 'ach',
            'account_number'      => '1234567890',
            'bank_name'           => 'Chase Bank',
            'purpose'             => 'Water borehole installation equipment',
            'purpose_description' => 'Direct payment to equipment vendor',
            'documents'           => ['https://res.cloudinary.com/fajiri/bill.pdf'],
        ]);

        $this->assertTrue($eval['passed']);
        $this->assertEquals('low', $eval['risk_level']);
        $this->assertEquals('otp', $eval['required_auth_method']); // > $2500 requires OTP
        $this->assertArrayHasKey('identity_verified', $eval['checks']);
        $this->assertArrayHasKey('sufficient_funds', $eval['checks']);
        $this->assertTrue($eval['checks']['sufficient_funds']['passed']);
    }

    public function test_compliance_flags_insufficient_balance_and_sanctioned_country(): void
    {
        $currencyService = app(CurrencyService::class);
        $financialsService = new CampaignFinancialsService($currencyService);
        $complianceService = new DisbursementComplianceService($financialsService);

        Role::create(['id' => 2, 'name' => 'Member 2', 'slug' => 'member-2']);

        $user = User::create([
            'email'             => 'owner2@example.com',
            'phone'             => '+2348011223345',
            'password'          => Hash::make('secret123'),
            'role_id'           => 2,
            'country_id'        => 1,
            'account_type'      => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'member_id'         => 'FAJ-TEST-OWNER-2',
        ]);

        $campaign = Campaign::create([
            'added_by'    => $user->id,
            'title'       => 'Medical Fund',
            'body'        => 'Urgent medical aid',
            'goal_amount' => 5000.0,
            'currency'    => 'USD',
            'status'      => 'active',
            'type'        => 'medical-aid',
        ]);

        // Campaign has 0 donations ($0 available)
        $eval = $complianceService->evaluateCompliance($campaign, $user, [
            'beneficiary_name'  => 'Patient John',
            'recipient_country' => 'KP', // North Korea (Sanctioned)
            'amount'            => 1000.0,
            'payout_method'     => 'local_bank_transfer',
            'account_number'    => '1234567890',
            'bank_name'         => 'Local Bank',
            'purpose'           => 'Medical bill',
        ]);

        $this->assertFalse($eval['passed']);
        $this->assertFalse($eval['checks']['country_supported']['passed']);
        $this->assertFalse($eval['checks']['sufficient_funds']['passed']);
        $this->assertTrue($eval['requires_admin_review']);
    }
}
