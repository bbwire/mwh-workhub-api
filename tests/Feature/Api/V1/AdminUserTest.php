<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_list_and_create_users(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/users')->assertOk();

        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Staff',
            'email' => 'new.staff@mswnh.test',
            'password' => 'password',
            'roles' => ['staff'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.staff@mswnh.test');
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/departments', ['name' => 'Blocked'])->assertForbidden();
    }

    public function test_admin_can_place_a_new_user_in_a_department(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $department = $this->postJson('/api/v1/admin/departments', [
            'name' => 'Laboratory',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'laboratory')
            ->json('data');

        $this->assertDatabaseHas('workspaces', [
            'department_id' => $department['id'],
            'slug' => 'laboratory',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Lab Officer',
            'email' => 'lab.officer@mswnh.test',
            'password' => 'password',
            'phone' => '0700000000',
            'roles' => ['staff'],
            'department_id' => $department['id'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'lab.officer@mswnh.test')
            ->assertJsonPath('data.primary_department_id', $department['id'])
            ->assertJsonPath('data.roles.0', 'staff');

        $user = User::query()->where('email', 'lab.officer@mswnh.test')->firstOrFail();
        $this->assertTrue($user->departments()->where('departments.id', $department['id'])->exists());
        $this->assertTrue($user->workspaces()->where('workspaces.department_id', $department['id'])->exists());

        $this->patchJson('/api/v1/admin/users/'.$user->id, [
            'name' => 'Lab Lead',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Lab Lead')
            ->assertJsonPath('data.primary_department_id', $department['id']);

        $this->patchJson('/api/v1/admin/departments/'.$department['id'], [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('workspaces', [
            'department_id' => $department['id'],
            'is_active' => false,
        ]);
    }
}
