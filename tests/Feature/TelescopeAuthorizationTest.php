<?php

namespace Tests\Feature;

use App\Enums\User\AccountType;
use App\Enums\User\Status;
use App\Models\Country;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelescopeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $_ENV['TELESCOPE_ENABLED'] = 'true';
        $_SERVER['TELESCOPE_ENABLED'] = 'true';
        putenv('TELESCOPE_ENABLED=true');

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        $_ENV['TELESCOPE_ENABLED'] = 'false';
        $_SERVER['TELESCOPE_ENABLED'] = 'false';
        putenv('TELESCOPE_ENABLED=false');

        parent::tearDown();
    }

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
    }

    public function test_guests_cannot_open_telescope(): void
    {
        $this->get('/telescope')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Telescope"');
    }

    public function test_guests_cannot_open_telescope_when_the_app_is_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/telescope')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="Telescope"');
    }

    public function test_members_cannot_open_telescope_with_basic_auth(): void
    {
        $member = $this->userForRole('user');

        $this->withBasicAuth($member->email, 'password')
            ->get('/telescope')
            ->assertForbidden();
    }

    public function test_admins_can_open_telescope_with_basic_auth(): void
    {
        $admin = $this->userForRole('admin');

        $this->withBasicAuth($admin->email, 'password')
            ->get('/telescope')
            ->assertOk();
    }

    public function test_super_admins_can_open_telescope_with_a_sanctum_token(): void
    {
        $admin = $this->userForRole('super-admin');
        $token = $admin->createToken('telescope')->plainTextToken;

        $this->withToken($token)
            ->get('/telescope')
            ->assertOk();
    }

    public function test_suspended_admins_cannot_open_telescope(): void
    {
        $admin = $this->userForRole('admin', Status::SUSPENDED);

        $this->withBasicAuth($admin->email, 'password')
            ->get('/telescope')
            ->assertForbidden();
    }

    private function userForRole(string $slug, Status $status = Status::ACTIVE): User
    {
        return User::create([
            'email' => $slug.'@example.com',
            'password' => 'password',
            'role_id' => Role::where('slug', $slug)->firstOrFail()->id,
            'country_id' => 1,
            'account_type' => AccountType::IDENTIFIED_MEMBERSHIP,
            'email_verified_at' => now(),
            'status' => $status,
        ]);
    }
}
