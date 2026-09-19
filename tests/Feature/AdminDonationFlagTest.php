<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Country;
use App\Models\Donation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDonationFlagTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Donation $donation;

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

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\AddAdminAccount::class);

        $this->admin = User::whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))->first();

        $this->donation = Donation::create([
            'donatable_id'   => (string) Str::uuid(),
            'donatable_type' => Campaign::class,
            'amount'         => 5000,
            'currency'       => 'NGN',
            'medium'         => 'paystack',
            'name'           => 'Test Donor',
            'email'          => 'donor@example.com',
            'status'         => 'completed',
            'reference'      => 'PAY_TEST123',
        ]);
    }

    /** @test */
    public function admin_can_view_a_single_donation()
    {
        $this->actingAs($this->admin)
            ->getJson("/v1/donations/{$this->donation->id}")
            ->assertOk()
            ->assertJsonPath('data.reference', 'PAY_TEST123')
            ->assertJsonPath('data.type', 'campaign')
            ->assertJsonPath('data.is_flagged', false);
    }

    /** @test */
    public function admin_can_flag_and_unflag_a_donation()
    {
        $this->actingAs($this->admin)
            ->postJson("/v1/donations/{$this->donation->id}/flag", ['reason' => 'Duplicate payment'])
            ->assertOk()
            ->assertJsonPath('data.is_flagged', true)
            ->assertJsonPath('data.flag_reason', 'Duplicate payment');

        $this->assertNotNull($this->donation->fresh()->flagged_at);
        $this->assertEquals($this->admin->id, $this->donation->fresh()->flagged_by);

        $this->actingAs($this->admin)
            ->deleteJson("/v1/donations/{$this->donation->id}/flag")
            ->assertOk()
            ->assertJsonPath('data.is_flagged', false);

        $this->assertNull($this->donation->fresh()->flagged_at);
    }

    /** @test */
    public function admin_can_filter_donations_by_type()
    {
        Donation::create([
            'donatable_id'   => (string) Str::uuid(),
            'donatable_type' => \App\Models\Need::class,
            'amount'         => 1000,
            'currency'       => 'NGN',
            'medium'         => 'wallet',
            'name'           => 'Need Donor',
            'email'          => 'need@example.com',
            'status'         => 'completed',
            'reference'      => 'WAL_NEED1',
        ]);

        $this->actingAs($this->admin)->getJson('/v1/donations?type=campaign')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'campaign');

        $this->actingAs($this->admin)->getJson('/v1/donations?type=need')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'need');

        $this->actingAs($this->admin)->getJson('/v1/donations')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    /** @test */
    public function flagging_requires_a_reason()
    {
        $this->actingAs($this->admin)
            ->postJson("/v1/donations/{$this->donation->id}/flag", [])
            ->assertStatus(422);
    }

    /** @test */
    public function guests_cannot_flag_donations()
    {
        $this->postJson("/v1/donations/{$this->donation->id}/flag", ['reason' => 'x'])
            ->assertStatus(401);
    }
}
