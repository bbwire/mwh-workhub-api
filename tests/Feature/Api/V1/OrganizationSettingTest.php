<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_authenticated_user_can_read_organization_settings(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->getJson('/api/v1/settings/organization')
            ->assertOk()
            ->assertJsonPath('data.short_name', 'MWH WorkHub')
            ->assertJsonStructure([
                'data' => [
                    'name',
                    'short_name',
                    'tagline',
                    'email',
                    'phone',
                    'logo_url',
                    'report_footer',
                ],
            ]);
    }

    public function test_admin_can_update_organization_settings_and_logo(): void
    {
        Storage::fake('public');

        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $this->actingAs($admin);

        $this->patchJson('/api/v1/settings/organization', [
            'name' => 'County Referral Hospital',
            'short_name' => 'CRH WorkHub',
            'tagline' => 'Care that connects',
            'email' => 'ops@crh.test',
            'phone' => '+254 711 000 000',
            'address' => 'Main Street',
            'report_footer' => 'Internal use only',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'County Referral Hospital')
            ->assertJsonPath('data.short_name', 'CRH WorkHub');

        $this->post('/api/v1/settings/organization/logo', [
            'file' => UploadedFile::fake()->create('logo.png', 40, 'image/png'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('data.logo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/'));
    }

    public function test_staff_cannot_update_organization_settings(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->patchJson('/api/v1/settings/organization', [
            'name' => 'Hacked Hospital',
        ])->assertForbidden();
    }
}
