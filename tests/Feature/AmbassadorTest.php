<?php

namespace Tests\Feature;

use App\Http\Services\CloudinaryService;
use App\Models\Ambassador;
use App\Models\Country;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AmbassadorTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

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

        $this->admin = User::whereHas('role', function ($query) {
            $query->where('slug', 'super-admin');
        })->first();
    }

    public function test_public_can_list_ambassadors_in_display_order(): void
    {
        Ambassador::create([
            'name' => 'Barrister Grace Emeson',
            'title' => 'Legal Counsel, Advisory & Board Member',
            'biography' => 'Legal counsel biography.',
            'photo' => 'https://cdn.example.com/grace.jpg',
            'sort_order' => 2,
        ]);
        Ambassador::create([
            'name' => 'Frank Ajirioghene Urefe',
            'title' => 'Chief Executive Officer',
            'biography' => 'Frank serves as chair of the governance committee.',
            'photo' => 'https://cdn.example.com/frank.jpg',
            'sort_order' => 0,
        ]);

        $this->getJson('/v1/ambassadors')
            ->assertOk()
            ->assertJsonPath('message', 'Ambassadors fetched successfully')
            ->assertJsonPath('data.0.name', 'Frank Ajirioghene Urefe')
            ->assertJsonPath('data.0.title', 'Chief Executive Officer')
            ->assertJsonPath('data.1.name', 'Barrister Grace Emeson');

        $this->getJson('/v1/ambassadors/frank-ajirioghene-urefe')
            ->assertOk()
            ->assertJsonPath('data.biography', 'Frank serves as chair of the governance committee.');
    }

    public function test_admin_can_create_update_and_delete_an_ambassador(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('uploadImage')
                ->twice()
                ->andReturn(
                    ['url' => 'https://cdn.example.com/frank.jpg', 'public_id' => 'ambassadors/frank'],
                    ['url' => 'https://cdn.example.com/frank-new.jpg', 'public_id' => 'ambassadors/frank-new'],
                );
        });

        $created = $this->actingAs($this->admin)
            ->post('/v1/ambassadors', [
                'name' => 'Frank Ajirioghene Urefe',
                'title' => 'Chief Executive Officer',
                'biography' => 'Board advisor and executive.',
                'sort_order' => 1,
                'photo' => UploadedFile::fake()->image('frank.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'frank-ajirioghene-urefe')
            ->assertJsonPath('data.photo', 'https://cdn.example.com/frank.jpg');

        $id = $created->json('data.id');

        $this->actingAs($this->admin)
            ->post("/v1/ambassadors/{$id}", [
                '_method' => 'PUT',
                'name' => 'Frank Ajirioghene Urefe',
                'title' => 'Executive Advisory',
                'biography' => 'Updated biography.',
                'sort_order' => 3,
                'photo' => UploadedFile::fake()->image('frank-new.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Executive Advisory')
            ->assertJsonPath('data.photo', 'https://cdn.example.com/frank-new.jpg');

        $this->actingAs($this->admin)
            ->deleteJson("/v1/ambassadors/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('ambassadors', ['id' => $id]);
    }

    public function test_a_photo_is_required_when_creating_an_ambassador(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/ambassadors', [
                'name' => 'Dr. Christy Alimi',
                'title' => 'Executive Advisory',
                'biography' => 'Advisory biography.',
            ])
            ->assertUnprocessable();
    }

    public function test_guests_cannot_manage_ambassadors(): void
    {
        $this->postJson('/v1/ambassadors', [
            'name' => 'Guest',
            'title' => 'Advisor',
            'biography' => 'Biography.',
        ])->assertUnauthorized();
    }
}
