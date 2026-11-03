<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_can_create_maintenance_request(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Leaking pipe in ward',
            'description' => 'Water pooling near nursing station.',
            'custom_fields' => [
                'location' => 'Block A',
                'safety_hazard' => true,
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.status.slug', 'submitted');

        $reference = $response->json('data.reference_number');
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-\d{5}$/', $reference);
    }
}
