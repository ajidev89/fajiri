<?php

namespace Tests\Feature;

use App\Http\Services\CloudinaryService;
use App\Models\Country;
use App\Models\Testimony;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TestimonyTest extends TestCase
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

    public function test_public_can_list_testimonies_in_display_order(): void
    {
        Testimony::create([
            'name' => 'Amaka Obi',
            'age' => 22,
            'story' => 'A later story.',
            'photo' => 'https://cdn.example.com/amaka.jpg',
            'sort_order' => 2,
        ]);
        Testimony::create([
            'name' => 'John Bieber',
            'age' => 15,
            'story' => 'After facing financial challenges while pursuing his education, Dominic received a community support program.',
            'photo' => 'https://cdn.example.com/john.jpg',
            'sort_order' => 0,
        ]);

        $this->getJson('/v1/testimonies')
            ->assertOk()
            ->assertJsonPath('message', 'Testimonies fetched successfully')
            ->assertJsonPath('data.0.name', 'John Bieber')
            ->assertJsonPath('data.0.age', 15)
            ->assertJsonPath('data.0.age_label', '15 y.o.')
            ->assertJsonPath('data.1.name', 'Amaka Obi');

        $this->getJson('/v1/testimonies/john-bieber')
            ->assertOk()
            ->assertJsonPath('data.story', 'After facing financial challenges while pursuing his education, Dominic received a community support program.');
    }

    public function test_admin_can_create_update_and_delete_a_testimony(): void
    {
        $this->mock(CloudinaryService::class, function ($mock) {
            $mock->shouldReceive('uploadImage')
                ->twice()
                ->andReturn(
                    ['url' => 'https://cdn.example.com/john.jpg', 'public_id' => 'testimonies/john'],
                    ['url' => 'https://cdn.example.com/john-new.jpg', 'public_id' => 'testimonies/john-new'],
                );
        });

        $created = $this->actingAs($this->admin)
            ->post('/v1/testimonies', [
                'name' => 'John Bieber',
                'age' => 15,
                'story' => 'A community support story.',
                'sort_order' => 1,
                'photo' => UploadedFile::fake()->image('john.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'john-bieber')
            ->assertJsonPath('data.photo', 'https://cdn.example.com/john.jpg')
            ->assertJsonPath('data.age_label', '15 y.o.');

        $id = $created->json('data.id');

        $this->actingAs($this->admin)
            ->post("/v1/testimonies/{$id}", [
                '_method' => 'PUT',
                'name' => 'John Bieber',
                'age' => 16,
                'story' => 'Updated story.',
                'sort_order' => 3,
                'photo' => UploadedFile::fake()->image('john-new.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.age', 16)
            ->assertJsonPath('data.story', 'Updated story.')
            ->assertJsonPath('data.photo', 'https://cdn.example.com/john-new.jpg');

        $this->actingAs($this->admin)
            ->deleteJson("/v1/testimonies/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('testimonies', ['id' => $id]);
    }

    public function test_a_photo_is_required_when_creating_a_testimony(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/testimonies', [
                'name' => 'John Bieber',
                'age' => 15,
                'story' => 'A community support story.',
            ])
            ->assertUnprocessable();
    }

    public function test_guests_cannot_manage_testimonies(): void
    {
        $this->postJson('/v1/testimonies', [
            'name' => 'Guest',
            'age' => 15,
            'story' => 'A story.',
        ])->assertUnauthorized();
    }
}
