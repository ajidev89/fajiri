<?php

namespace Tests\Feature;

use App\Enums\Donations\Medium;
use App\Models\Campaign;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrunePendingDonationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_pending_donations_older_than_24_hours(): void
    {
        $expired = $this->createDonation('pending', now()->subHours(24));
        $recent = $this->createDonation('pending', now()->subHours(23)->subMinutes(59));
        $completed = $this->createDonation('completed', now()->subDays(2));
        $failed = $this->createDonation('failed', now()->subDays(2));

        $this->artisan('donations:prune-pending')
            ->expectsOutputToContain('Removed 1 pending donation(s)')
            ->assertSuccessful();

        $this->assertDatabaseMissing('donations', ['id' => $expired->id]);
        $this->assertDatabaseHas('donations', ['id' => $recent->id, 'status' => 'pending']);
        $this->assertDatabaseHas('donations', ['id' => $completed->id, 'status' => 'completed']);
        $this->assertDatabaseHas('donations', ['id' => $failed->id, 'status' => 'failed']);
    }

    public function test_prune_pending_donations_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('donations:prune-pending')
            ->assertSuccessful();
    }

    private function createDonation(string $status, Carbon $createdAt): Donation
    {
        $donation = Donation::create([
            'donatable_id' => (string) Str::uuid(),
            'donatable_type' => Campaign::class,
            'amount' => 1000,
            'currency' => 'NGN',
            'converted_amount' => 1000,
            'rate' => 1,
            'medium' => Medium::PAYSTACK,
            'name' => 'Donor',
            'email' => 'donor@example.com',
            'status' => $status,
            'reference' => 'PAY_'.Str::uuid(),
        ]);

        Donation::query()->whereKey($donation->id)->update([
            'created_at' => $createdAt,
        ]);

        return $donation->fresh();
    }
}
