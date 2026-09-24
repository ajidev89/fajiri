<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Jobs\SendGlobalAnnouncementJob;
use App\Models\Announcement;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnounceBirthdaysTest extends TestCase
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

    public function test_it_groups_everyone_with_a_birthday_today_into_one_announcement(): void
    {
        $this->travelTo('2026-09-24 08:00:00');

        $this->createMember('Ajidagba', 'Ade', '1992-09-24');
        $this->createMember('Ike', 'Okafor', '1988-09-24');
        $this->createMember('Tolu', 'Balogun', '2001-09-24');
        $this->createMember('Gift', 'Later', '1995-09-25');
        $this->createMember('Suspended', 'User', '1990-09-24', Status::SUSPENDED);

        Bus::fake();

        $this->artisan('birthdays:announce')
            ->expectsOutputToContain('Announced birthdays for Ajidagba, Ike and Tolu.')
            ->assertSuccessful();

        $this->assertSame(1, Announcement::query()->count());

        $announcement = Announcement::query()->first();
        $this->assertSame('Happy Birthday', $announcement->title);
        $this->assertSame(['all'], $announcement->target_audience);
        $this->assertStringContainsString(
            'Ajidagba, Ike and Tolu all have their birthdays today.',
            $announcement->content
        );
        $this->assertStringContainsString(
            'Please take a moment to gift them and appreciate the joy they bring to our community.',
            $announcement->content
        );

        Bus::assertDispatched(SendGlobalAnnouncementJob::class);
    }

    public function test_a_single_birthday_is_announced_by_name(): void
    {
        $this->travelTo('2026-04-02 08:00:00');
        $this->createMember('Gifty', 'Mensah', '1999-04-02');

        Bus::fake();

        $this->artisan('birthdays:announce')->assertSuccessful();

        $this->assertStringContainsString(
            'Gifty has a birthday today.',
            Announcement::query()->first()->content
        );
        $this->assertStringContainsString(
            'Wishing Gifty a joyful birthday.',
            Announcement::query()->first()->content
        );
    }

    public function test_it_does_not_announce_the_same_birthdays_twice_in_one_day(): void
    {
        $this->travelTo('2026-09-24 08:00:00');
        $this->createMember('Ajidagba', 'Ade', '1992-09-24');

        Bus::fake();

        $this->artisan('birthdays:announce')->assertSuccessful();
        $this->artisan('birthdays:announce')
            ->expectsOutputToContain('Birthday announcements were already sent today.')
            ->assertSuccessful();

        $this->assertSame(1, Announcement::query()->count());
        Bus::assertDispatchedTimes(SendGlobalAnnouncementJob::class, 1);
    }

    public function test_february_29_birthdays_are_announced_on_february_28_in_non_leap_years(): void
    {
        $this->travelTo('2026-02-28 08:00:00');
        $this->createMember('Ada', 'Nwosu', '2000-02-29');

        Bus::fake();

        $this->artisan('birthdays:announce')->assertSuccessful();

        $this->assertStringContainsString(
            'Ada has a birthday today.',
            Announcement::query()->first()->content
        );
    }

    public function test_birthday_announcements_are_scheduled_daily(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('birthdays:announce')
            ->assertSuccessful();
    }

    private function createMember(string $firstName, string $lastName, string $dob, Status $status = Status::ACTIVE): User
    {
        $user = User::create([
            'email' => strtolower($firstName).'.'.strtolower($lastName).'@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $this->userRole->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => $status,
        ]);

        $user->profile()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => 'male',
            'dob' => $dob,
        ]);

        return $user;
    }
}
