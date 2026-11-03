<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkItemAssignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_supervisor_can_assign_and_list_assignees(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'AC not cooling',
            'description' => 'Office AC warm.',
            'custom_fields' => ['location' => 'Admin block'],
        ])->json('data.id');

        $this->actingAs($supervisor);

        $this->getJson("/api/v1/work-items/{$workItemId}/assignees")
            ->assertOk()
            ->assertJsonFragment(['email' => 'facilities.officer@mswnh.test']);

        $this->postJson("/api/v1/work-items/{$workItemId}/assign", [
            'assignee_id' => $officer->id,
            'reason' => 'Facilities queue',
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $officer->id);
    }

    public function test_requester_cannot_assign(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Door hinge loose',
            'description' => 'Ward door.',
            'custom_fields' => ['location' => 'Ward 1'],
        ])->json('data.id');

        $this->postJson("/api/v1/work-items/{$workItemId}/assign", [
            'assignee_id' => $officer->id,
        ])->assertForbidden();
    }
}
