<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\AddAdminAccount;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
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

    public function test_public_can_list_faqs_by_type(): void
    {
        Faq::create([
            'type' => 'donations',
            'question' => 'How can I donate?',
            'answer' => 'You can donate from a campaign page.',
            'sort_order' => 2,
        ]);
        Faq::create([
            'type' => 'general',
            'question' => 'Is Fajiri a global charity?',
            'answer' => 'Yes. Fajiri Family Relief Foundation supports families globally.',
            'sort_order' => 1,
        ]);
        Faq::create([
            'type' => 'general',
            'question' => 'Is the FFRF a Club?',
            'answer' => 'No. It is a charity foundation.',
            'sort_order' => 0,
        ]);

        $this->getJson('/v1/faqs?type=general')
            ->assertOk()
            ->assertJsonPath('message', 'FAQs fetched successfully')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.question', 'Is the FFRF a Club?')
            ->assertJsonPath('data.0.type', 'general')
            ->assertJsonPath('data.0.type_label', 'General')
            ->assertJsonPath('data.1.question', 'Is Fajiri a global charity?');

        $this->getJson('/v1/faqs/types')
            ->assertOk()
            ->assertJsonPath('data.0.value', 'general')
            ->assertJsonPath('data.0.label', 'General')
            ->assertJsonPath('data.1.label', 'Donations')
            ->assertJsonPath('data.2.label', 'Members');
    }

    public function test_admin_can_create_update_and_delete_a_faq(): void
    {
        $created = $this->actingAs($this->admin)
            ->postJson('/v1/admin/faqs', [
                'type' => 'members',
                'question' => 'Do I have to pay membership dues and levies?',
                'answer' => 'Membership plans are optional until you subscribe.',
                'sort_order' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'members')
            ->assertJsonPath('data.question', 'Do I have to pay membership dues and levies?');

        $id = $created->json('data.id');

        $this->actingAs($this->admin)
            ->putJson("/v1/admin/faqs/{$id}", [
                'type' => 'donations',
                'question' => 'Can I get a refund?',
                'answer' => 'Refunds are reviewed by the finance team.',
                'sort_order' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.type', 'donations')
            ->assertJsonPath('data.question', 'Can I get a refund?');

        $this->actingAs($this->admin)
            ->getJson('/v1/admin/faqs?type=donations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);

        $this->actingAs($this->admin)
            ->deleteJson("/v1/admin/faqs/{$id}")
            ->assertOk();

        $this->assertDatabaseMissing('faqs', ['id' => $id]);
    }

    public function test_faq_type_must_be_one_of_the_supported_types(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/v1/admin/faqs', [
                'type' => 'events',
                'question' => 'When is the next event?',
                'answer' => 'See the events page.',
            ])
            ->assertUnprocessable();
    }

    public function test_guests_cannot_manage_faqs(): void
    {
        $this->postJson('/v1/admin/faqs', [
            'type' => 'general',
            'question' => 'Question',
            'answer' => 'Answer',
        ])->assertUnauthorized();
    }
}
