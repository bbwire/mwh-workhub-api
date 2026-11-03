<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_user_can_login_and_fetch_profile(): void
    {
        $this->startSession();

        $response = $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/v1/auth/login', [
                'email' => 'staff@mswnh.test',
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'staff@mswnh.test');

        $this->getJson('/api/v1/auth/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'staff@mswnh.test')
            ->assertJsonStructure(['data' => ['roles', 'permissions']]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::query()->where('email', 'staff@mswnh.test')->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'staff@mswnh.test',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'staff@mswnh.test',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
