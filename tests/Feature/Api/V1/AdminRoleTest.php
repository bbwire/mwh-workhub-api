<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_list_roles_and_permissions(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/roles')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'label', 'is_protected', 'users_count', 'permissions'],
                ],
                'meta' => [
                    'permissions',
                    'protected_roles',
                ],
            ])
            ->assertJsonFragment(['name' => 'super_admin']);
    }

    public function test_admin_can_create_and_update_custom_role(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $create = $this->postJson('/api/v1/admin/roles', [
            'name' => 'ward_clerk',
            'permissions' => ['work.create', 'work.view.team'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'ward_clerk')
            ->assertJsonPath('data.is_protected', false);

        $roleId = $create->json('data.id');

        $this->patchJson("/api/v1/admin/roles/{$roleId}", [
            'permissions' => ['work.create', 'work.update.assigned', 'work.view.team'],
        ])
            ->assertOk()
            ->assertJsonPath('data.permissions', [
                'work.create',
                'work.update.assigned',
                'work.view.team',
            ]);
    }

    public function test_protected_role_cannot_be_deleted_or_renamed(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $role = Role::query()->where('name', 'staff')->firstOrFail();

        $this->patchJson("/api/v1/admin/roles/{$role->id}", [
            'name' => 'team_member',
        ])->assertUnprocessable();

        $this->deleteJson("/api/v1/admin/roles/{$role->id}")
            ->assertUnprocessable();
    }

    public function test_staff_cannot_manage_roles(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_admin_can_delete_unused_custom_role(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $role = Role::query()->create([
            'name' => 'temp_role',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo('work.create');

        $this->deleteJson("/api/v1/admin/roles/{$role->id}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
