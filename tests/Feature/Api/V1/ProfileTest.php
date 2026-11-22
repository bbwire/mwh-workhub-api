<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_update_own_profile(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Updated Staff Name',
            'phone' => '+256 700 111 222',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Staff Name')
            ->assertJsonPath('data.phone', '+256 700 111 222')
            ->assertJsonPath('data.email', 'staff@mswnh.test');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Updated Staff Name',
            'phone' => '+256 700 111 222',
            'email' => 'staff@mswnh.test',
        ]);
    }

    public function test_user_cannot_change_email_via_profile(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Still Staff',
            'phone' => null,
            'email' => 'hacker@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'staff@mswnh.test');

        $this->assertSame('staff@mswnh.test', $staff->fresh()->email);
    }

    public function test_user_can_change_password(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->postJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password updated.');

        $this->assertTrue(Hash::check('new-password-123', $staff->fresh()->password));
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->postJson('/api/v1/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('password', $staff->fresh()->password));
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/v1/auth/profile', [
            'name' => 'Nope',
        ])->assertUnauthorized();
    }

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->post('/api/v1/auth/avatar', [
            'file' => UploadedFile::fake()->create('avatar.png', 40, 'image/png'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.avatar_url', fn ($url) => is_string($url) && str_contains($url, '/storage/'));

        $staff->refresh();
        $this->assertNotNull($staff->avatar_path);
        Storage::disk('public')->assertExists($staff->avatar_path);

        $this->deleteJson('/api/v1/auth/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        $this->assertNull($staff->fresh()->avatar_path);
    }
}
