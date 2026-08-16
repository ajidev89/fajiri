<?php

namespace Tests\Feature;

use App\Enums\Disbursement\Status;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\Donation;
use App\Models\Otp;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisbursementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a dummy country with ID 1 to satisfy seeders
        Country::create([
            'id'         => 1,
            'name'       => 'Nigeria',
            'iso3'       => 'NGA',
            'iso2'       => 'NG',
            'currency'   => 'NGN',
            'phone_code' => '+234',
        ]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\AddAdminAccount::class);

        $userRole = Role::where('slug', 'user')->first();

        $this->user = User::create([
            'email'             => 'campaigner@example.com',
            'phone'             => '+2348000000001',
            'password'          => Hash::make('password123'),
            'role_id'           => $userRole->id,
            'country_id'        => 1,
            'account_type'      => \App\Enums\User\AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'member_id'         => 'FAJ-CAMP-001',
        ]);

        $this->admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        $this->campaign = Campaign::create([
            'added_by'    => $this->user->id,
            'title'       => 'Hospital Treatment Support',
            'body'        => 'Medical fund for patient',
            'goal_amount' => 50000.0,
            'currency'    => 'USD',
            'status'      => 'active',
            'type'        => 'medical-aid',
        ]);

        // Add completed donations to give available balance
        Donation::create([
            'donatable_type'   => Campaign::class,
            'donatable_id'     => $this->campaign->id,
            'amount'           => 30000.0,
            'converted_amount' => 30000.0,
            'rate'             => 1.0,
            'currency'         => 'USD',
            'fee'              => 750.0,
            'status'           => 'completed',
        ]);
    }

    public function test_get_campaign_financials_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/v1/campaigns/{$this->campaign->id}/disbursements/financials");

        $response->assertStatus(200);
        $response->assertJsonPath('data.total_raised', 30000);
        $response->assertJsonPath('data.platform_fees', 750);
        $response->assertJsonPath('data.available_balance', 29250);
    }

    public function test_validate_disbursement_modal_step(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/v1/campaigns/{$this->campaign->id}/disbursements/validate", [
            'beneficiary_name'    => 'John Smith',
            'recipient_type'      => 'individual_beneficiary',
            'recipient_country'   => 'US',
            'amount'              => 5000.0,
            'payout_method'       => 'ach',
            'account_number'      => '9876543210',
            'bank_name'           => 'Chase Bank',
            'purpose'             => 'Hospital treatment bill',
            'purpose_description' => 'Medical surgery expenses',
            'documents'           => ['https://res.cloudinary.com/fajiri/bill.pdf'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.compliance.passed', true);
        $response->assertJsonPath('data.fee_calculation.requested_amount', 5000);
    }

    public function test_send_and_verify_disbursement_otp_flow(): void
    {
        Sanctum::actingAs($this->user);

        // 1. Request OTP
        $otpRes = $this->postJson("/v1/campaigns/{$this->campaign->id}/disbursements/send-otp");
        $otpRes->assertStatus(200);

        // Retrieve created OTP token and submit with correct code
        Otp::updateOrCreate(
            ['identifier' => $this->user->email, 'channel' => 'email'],
            [
                'hash'       => Hash::make('123456'),
                'expires_at' => now()->addMinutes(10),
                'verified'   => false,
            ]
        );

        // 2. Submit Disbursement
        $disburseRes = $this->postJson("/v1/campaigns/{$this->campaign->id}/disbursements", [
            'beneficiary_name'    => 'John Smith',
            'recipient_type'      => 'individual_beneficiary',
            'recipient_country'   => 'US',
            'amount'              => 5000.0,
            'payout_method'       => 'ach',
            'account_number'      => '9876543210',
            'bank_name'           => 'Chase Bank',
            'purpose'             => 'Hospital treatment bill',
            'purpose_description' => 'Medical surgery expenses',
            'documents'           => ['https://res.cloudinary.com/fajiri/bill.pdf'],
            'otp'                 => '123456',
        ]);

        $disburseRes->assertStatus(201);
        $disburseRes->assertJsonPath('data.data.beneficiary_name', 'John Smith');
        $disburseRes->assertJsonPath('data.data.amount', 5000);
        $this->assertStringStartsWith('DSB-', $disburseRes->json('data.data.disbursement_code'));

        $disbursementId = $disburseRes->json('data.data.id');

        // 3. Admin review and approve
        Sanctum::actingAs($this->admin);
        $approveRes = $this->postJson("/v1/admin/disbursements/{$disbursementId}/approve");
        $approveRes->assertStatus(200);
    }
}
