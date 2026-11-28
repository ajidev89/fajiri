<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\LegalDocument;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
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

    public function test_admin_list_includes_every_type_even_when_unwritten(): void
    {
        LegalDocument::create([
            'type' => 'terms',
            'title' => 'Terms of Service',
            'content' => '<p>Be kind.</p>',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/v1/admin/legal')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'terms')
            ->assertJsonPath('data.0.content', '<p>Be kind.</p>')
            ->assertJsonPath('data.1.type', 'privacy')
            ->assertJsonPath('data.1.type_label', 'Privacy Policy')
            ->assertJsonPath('data.1.id', null)
            ->assertJsonPath('data.1.content', '');
    }

    public function test_admin_can_create_then_update_a_document(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/v1/admin/legal/privacy', [
                'title' => 'Privacy Policy',
                'content' => '<p>We keep your data safe.</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'privacy')
            ->assertJsonPath('data.content', '<p>We keep your data safe.</p>');

        $this->actingAs($this->admin)
            ->putJson('/v1/admin/legal/privacy', [
                'title' => 'Our Privacy Policy',
                'content' => '<p>Updated.</p>',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Our Privacy Policy');

        $this->assertDatabaseCount('legal_documents', 1);
    }

    public function test_public_can_read_a_document(): void
    {
        LegalDocument::create([
            'type' => 'terms',
            'title' => 'Terms of Service',
            'content' => '<p>Be kind.</p>',
        ]);

        $this->getJson('/v1/legal/terms')
            ->assertOk()
            ->assertJsonPath('data.title', 'Terms of Service')
            ->assertJsonPath('data.content', '<p>Be kind.</p>');

        $this->getJson('/v1/legal/privacy')->assertNotFound();
        $this->getJson('/v1/legal/cookies')->assertNotFound();
    }

    public function test_validation_and_auth(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/v1/admin/legal/terms', ['title' => '', 'content' => ''])
            ->assertUnprocessable();

        $this->actingAs($this->admin)
            ->putJson('/v1/admin/legal/cookies', ['title' => 'x', 'content' => 'y'])
            ->assertNotFound();

        $this->app['auth']->forgetGuards();

        $this->putJson('/v1/admin/legal/terms', ['title' => 'x', 'content' => 'y'])
            ->assertUnauthorized();
    }
}
