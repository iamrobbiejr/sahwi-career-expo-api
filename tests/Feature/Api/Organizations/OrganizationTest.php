<?php

namespace Tests\Feature\Api\Organizations;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_organizations_for_frontend()
    {
        Organization::factory()->create(['name' => 'Apple Inc']);
        Organization::factory()->create(['name' => 'Microsoft']);
        Organization::factory()->count(10)->create();

        $response = $this->getJson('/api/v1/organizations/search?search=Apple');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Apple Inc'])
            ->assertJsonStructure([
                '*' => ['id', 'name']
            ]);

        // Test limit
        $response = $this->getJson('/api/v1/organizations/search');
        $response->assertStatus(200)
            ->assertJsonCount(8);
    }

    public function test_can_list_organizations_with_pagination_and_filters()
    {
        Organization::factory()->create(['name' => 'Tech Corp', 'type' => 'company', 'verified' => true]);
        Organization::factory()->create(['name' => 'Edu Inst', 'type' => 'university', 'verified' => false]);

        $response = $this->getJson('/api/v1/organizations?type=company&verified=1');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Tech Corp')
            ->assertJsonStructure([
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ]);
    }

    public function test_can_get_organization_details_with_members()
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['role' => 'student']);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'admin'
        ]);

        $response = $this->getJson("/api/v1/organizations/{$organization->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => $organization->name])
            ->assertJsonStructure([
                'id',
                'name',
                'members' => [
                    '*' => [
                        'id',
                        'role',
                        'user' => ['id', 'name', 'email']
                    ]
                ]
            ]);
    }
}
