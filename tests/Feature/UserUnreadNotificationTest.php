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

class UserUnreadNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_includes_unread_notification_count(): void
    {
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

        $user = User::create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
            'role_id' => Role::where('slug', 'user')->value('id'),
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Unread one',
            'message' => 'Please read this',
            'type' => 'announcement',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Unread two',
            'message' => 'Please read this too',
            'type' => 'announcement',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Already read',
            'message' => 'Seen',
            'type' => 'announcement',
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/v1/user')
            ->assertOk()
            ->assertJsonPath('data.unread_notification', 2);
    }
}
