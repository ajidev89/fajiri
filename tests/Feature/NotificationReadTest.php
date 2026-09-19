<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Models\Country;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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

        $this->user = User::create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
            'role_id' => Role::where('slug', 'user')->value('id'),
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'New announcement',
            'message' => 'Please read this',
            'type' => 'announcement',
        ]);

        $this->actingAs($this->user)
            ->postJson("/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($this->user)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.unread_notification', 0);
    }

    public function test_user_cannot_read_someone_elses_notification(): void
    {
        $other = User::create([
            'email' => 'other@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->user->role_id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $notification = Notification::create([
            'user_id' => $other->id,
            'title' => 'Private',
            'message' => 'Not yours',
            'type' => 'announcement',
        ]);

        $this->actingAs($this->user)
            ->postJson("/v1/notifications/{$notification->id}/read")
            ->assertStatus(400);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_guest_cannot_read_a_notification(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'New announcement',
            'message' => 'Please read this',
            'type' => 'announcement',
        ]);

        $this->postJson("/v1/notifications/{$notification->id}/read")
            ->assertStatus(401);
    }
}
