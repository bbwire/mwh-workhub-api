<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_supervisor_can_create_project_task_and_board(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();

        $this->actingAs($supervisor);

        $projectId = $this->postJson('/api/v1/projects', [
            'name' => 'Ward renovation',
            'description' => 'Phase 1 upgrades',
            'member_ids' => [$officer->id],
            'milestones' => [
                ['title' => 'Design sign-off', 'due_at' => now()->addMonth()->toIso8601String()],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ward renovation')
            ->json('data.id');

        $taskId = $this->postJson("/api/v1/projects/{$projectId}/tasks", [
            'title' => 'Order materials',
            'assignee_id' => $officer->id,
            'due_at' => now()->addWeek()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', $projectId)
            ->json('data.id');

        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('tasks.0.id', $taskId);

        $this->getJson("/api/v1/projects/{$projectId}/board")
            ->assertOk()
            ->assertJsonPath('data.project.id', $projectId)
            ->assertJsonStructure(['data' => ['columns']]);

        $this->actingAs($officer);

        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk();
    }

    public function test_staff_not_on_project_cannot_view(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($supervisor);

        $projectId = $this->postJson('/api/v1/projects', [
            'name' => 'Private initiative',
        ])->assertCreated()->json('data.id');

        $this->actingAs($staff);

        $this->getJson("/api/v1/projects/{$projectId}")->assertForbidden();
    }
}
