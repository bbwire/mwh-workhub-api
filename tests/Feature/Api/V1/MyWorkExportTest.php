<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyWorkExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_can_export_my_work_csv(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $response = $this->get('/api/v1/my-work/export', [
            'Accept' => 'text/csv',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('reference_number', $response->streamedContent());
    }

    public function test_staff_can_export_my_work_pdf(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->get('/api/v1/my-work/pdf', [
            'Accept' => 'application/pdf',
        ])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_guest_cannot_export_my_work(): void
    {
        $this->getJson('/api/v1/my-work/export')->assertUnauthorized();
        $this->getJson('/api/v1/my-work/pdf')->assertUnauthorized();
    }
}
