<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Http\Services\FirebaseNotification;
use App\Jobs\SendGlobalAnnouncementJob;
use App\Mail\AnnouncementMail;
use App\Models\Announcement;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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
        $this->seed(AddAdminAccount::class);

        $this->admin = User::whereHas('role', function ($q) {
            $q->where('slug', 'super-admin');
        })->first();

        $this->userRole = Role::where('slug', 'user')->first();
    }

    public function test_creating_an_announcement_adds_it_to_the_user_notification_list(): void
    {
        $member = $this->createMember('member@example.com', 'fcm-token-123');
        $memberWithoutToken = $this->createMember('notoken@example.com');

        Mail::fake();

        $firebase = Mockery::mock(FirebaseNotification::class);
        $firebase->shouldReceive('pushNotificationBatch')->once();
        $this->app->instance(FirebaseNotification::class, $firebase);

        $this->actingAs($this->admin)
            ->postJson('/v1/admin/announcements', [
                'title' => 'Community Update',
                'content' => 'New programs are live this week.',
                'target_audience' => ['all'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $member->id,
            'title' => 'Community Update',
            'message' => 'New programs are live this week.',
            'type' => 'announcement',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $memberWithoutToken->id,
            'title' => 'Community Update',
            'type' => 'announcement',
        ]);

        $this->actingAs($member)
            ->getJson('/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Community Update')
            ->assertJsonPath('data.0.type', 'announcement');

        Mail::assertQueued(AnnouncementMail::class, function (AnnouncementMail $mail) use ($member) {
            return $mail->hasTo($member->email)
                && $mail->announcement->title === 'Community Update';
        });

        Mail::assertQueued(AnnouncementMail::class, function (AnnouncementMail $mail) use ($memberWithoutToken) {
            return $mail->hasTo($memberWithoutToken->email);
        });
    }

    public function test_admin_can_list_announcements(): void
    {
        Announcement::create([
            'title' => 'Community Update',
            'content' => 'New programs are live this week.',
            'target_audience' => ['all'],
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/admin/announcements')
            ->assertOk()
            ->assertJsonPath('message', 'Successfully fetched announcements')
            ->assertJsonPath('data.0.title', 'Community Update');
    }

    public function test_announcement_notifications_respect_target_audience(): void
    {
        $targeted = $this->createMember('targeted@example.com');
        $other = $this->createMember(
            'corporate@example.com',
            null,
            AccountType::CORPORATE_MEMBERSHIP
        );

        Mail::fake();

        $firebase = Mockery::mock(FirebaseNotification::class);
        $firebase->shouldReceive('pushNotificationBatch')->once();
        $this->app->instance(FirebaseNotification::class, $firebase);

        $announcement = Announcement::create([
            'title' => 'FIM Only',
            'content' => 'This is for identified members.',
            'target_audience' => ['fim'],
        ]);

        (new SendGlobalAnnouncementJob($announcement))->handle($firebase);

        $this->assertTrue(
            Notification::where('user_id', $targeted->id)->where('type', 'announcement')->exists()
        );
        $this->assertFalse(
            Notification::where('user_id', $other->id)->where('type', 'announcement')->exists()
        );

        Mail::assertQueued(AnnouncementMail::class, function (AnnouncementMail $mail) use ($targeted) {
            return $mail->hasTo($targeted->email);
        });

        Mail::assertNotQueued(AnnouncementMail::class, function (AnnouncementMail $mail) use ($other) {
            return $mail->hasTo($other->email);
        });
    }

    protected function createMember(
        string $email,
        ?string $notificationToken = null,
        AccountType $accountType = AccountType::IDENTIFIED_MEMBERSHIP
    ): User {
        $user = User::create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => $accountType,
            'email_verified_at' => now(),
            'status' => 'active',
            'notification_token' => $notificationToken,
        ]);

        $user->profile()->create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'gender' => 'male',
            'dob' => '1992-01-01',
        ]);

        return $user;
    }
}
