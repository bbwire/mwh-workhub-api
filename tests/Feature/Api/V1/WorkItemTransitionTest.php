<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkItemTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Test',
            'description' => 'Test',
            'custom_fields' => ['location' => 'A'],
        ])->json('data.id');

        $this->postJson("/api/v1/work-items/{$workItemId}/transitions", [
            'transition' => 'close',
        ])->assertUnprocessable();
    }

    public function test_created_request_appears_on_facilities_board(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $reference = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Board visibility test',
            'description' => 'Should appear in submitted column.',
            'custom_fields' => ['location' => 'Lab'],
        ])->json('data.reference_number');

        $this->actingAs($supervisor);

        $board = $this->getJson('/api/v1/workspaces/facilities/board')->assertOk()->json('data.columns');
        $references = collect($board)->flatMap(fn ($col) => collect($col['items'])->pluck('reference_number'));

        $this->assertTrue($references->contains($reference));
    }
}
