<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Http\Services\FirebaseNotification;
use App\Mail\MembershipUnpaidReminderMail;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class RemindUnpaidMembershipsTest extends TestCase
{
    use RefreshDatabase;

    protected Role $userRole;

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

        $this->userRole = Role::where('slug', 'user')->first();
    }

    public function test_it_reminds_people_who_have_never_paid_to_subscribe(): void
    {
        $neverPaid = $this->createMember('never-paid@example.com');

        $current = $this->createMember('current@example.com');
        $this->attachPlan($current, $this->createPlan('Gold', 25000), now()->addDays(10));

        $lapsed = $this->createMember('lapsed@example.com');
        $this->attachPlan($lapsed, $this->createPlan('Bronze', 5000), now()->subDay());

        $optedOut = $this->createMember('quiet@example.com');
        $optedOut->preference()->update(['membership_status_updates' => false]);

        Mail::fake();
        $this->mockFirebase();

        $this->artisan('memberships:remind-unpaid')
            ->expectsOutputToContain('Sent 1 subscribe reminder(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $neverPaid->id,
            'type' => 'membership_subscribe_reminder',
            'title' => 'Subscribe to a membership',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $current->id,
            'type' => 'membership_subscribe_reminder',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $lapsed->id,
            'type' => 'membership_subscribe_reminder',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $optedOut->id,
            'type' => 'membership_subscribe_reminder',
        ]);

        Mail::assertQueued(MembershipUnpaidReminderMail::class, function (MembershipUnpaidReminderMail $mail) use ($neverPaid) {
            return $mail->hasTo($neverPaid->email);
        });
        Mail::assertNotQueued(MembershipUnpaidReminderMail::class, function (MembershipUnpaidReminderMail $mail) use ($current, $lapsed) {
            return $mail->hasTo($current->email) || $mail->hasTo($lapsed->email);
        });
    }

    public function test_it_repeats_the_subscribe_reminder_weekly(): void
    {
        $this->travelTo('2026-09-24 09:00:00');

        $neverPaid = $this->createMember('repeat@example.com');

        Mail::fake();
        $firebase = $this->mockFirebase(2);

        $this->artisan('memberships:remind-unpaid')->assertSuccessful();
        $this->artisan('memberships:remind-unpaid')
            ->expectsOutputToContain('Sent 0 subscribe reminder(s).')
            ->assertSuccessful();

        $this->travel(6)->days();
        $this->artisan('memberships:remind-unpaid')
            ->expectsOutputToContain('Sent 0 subscribe reminder(s).')
            ->assertSuccessful();

        $this->travel(1)->days();
        $this->artisan('memberships:remind-unpaid')
            ->expectsOutputToContain('Sent 1 subscribe reminder(s).')
            ->assertSuccessful();

        $this->assertSame(2, Notification::query()->where('user_id', $neverPaid->id)->count());
        $firebase->shouldHaveReceived('pushNotificationBatch')->twice();
    }

    public function test_unpaid_membership_reminders_are_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('memberships:remind-unpaid')
            ->assertSuccessful();
    }

    private function mockFirebase(int $times = 1): FirebaseNotification
    {
        $firebase = Mockery::mock(FirebaseNotification::class);
        $firebase->shouldReceive('pushNotificationBatch')->times($times);
        $this->app->instance(FirebaseNotification::class, $firebase);

        return $firebase;
    }

    private function createPlan(string $name, float $price): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => $price,
            'currency' => 'NGN',
            'duration' => 30,
            'status' => true,
        ]);
    }

    private function createMember(string $email, Status $status = Status::ACTIVE): User
    {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => $status,
        ]);

        $user->profile()->create([
            'first_name' => 'Ada',
            'last_name' => 'Member',
            'gender' => 'female',
            'dob' => '1992-01-01',
        ]);

        return $user;
    }

    private function attachPlan(User $user, Plan $plan, Carbon $expiresAt): void
    {
        $user->plans()->attach($plan->id, [
            'id' => (string) Str::uuid(),
            'started_at' => $expiresAt->copy()->subDays($plan->duration),
            'expires_at' => $expiresAt,
            'status' => 'active',
            'auto_renew' => true,
        ]);
    }
}
