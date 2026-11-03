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
    }
}
