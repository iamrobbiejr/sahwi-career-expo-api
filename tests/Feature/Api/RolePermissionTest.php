<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'view_roles']);
        Permission::create(['name' => 'manage_roles']);

        // Create roles
        $this->adminRole = Role::create(['name' => 'admin']);
        $this->userRole = Role::create(['name' => 'user']);
    }

    public function test_admin_can_list_roles()
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->adminRole);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/roles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'permissions']
            ]);
    }

    public function test_admin_can_list_permissions()
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->adminRole);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name']
            ]);
    }

    public function test_admin_can_update_role_permissions()
    {
        $admin = User::factory()->create();
        $admin->assignRole($this->adminRole);

        $roleToUpdate = Role::create(['name' => 'editor']);
        $permissionToAssign = Permission::create(['name' => 'edit_content']);

        $response = $this->actingAs($admin)->postJson("/api/v1/admin/roles/{$roleToUpdate->id}/permissions", [
            'permissions' => ['edit_content']
        ]);

        $response->assertStatus(200);
        $this->assertTrue($roleToUpdate->fresh()->hasPermissionTo('edit_content'));
    }

    public function test_non_admin_cannot_access_endpoints()
    {
        $user = User::factory()->create();
        $user->assignRole($this->userRole);

        $this->actingAs($user)->getJson('/api/v1/admin/roles')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/admin/permissions')->assertStatus(403);

        $role = Role::first();
        $this->actingAs($user)->postJson("/api/v1/admin/roles/{$role->id}/permissions", [])->assertStatus(403);
    }
}
