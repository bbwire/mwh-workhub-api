<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_management_can_create_meeting_and_bulk_actions(): void
    {
        $management = User::query()->where('email', 'management@mswnh.test')->firstOrFail();
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();

        $this->actingAs($management);

        $meetingId = $this->postJson('/api/v1/meetings', [
            'title' => 'Monthly ops review',
            'held_at' => now()->toIso8601String(),
            'notes' => 'Track facility actions.',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/meetings/{$meetingId}/actions/bulk", [
            'actions' => [
                [
                    'title' => 'Replace ward signage',
                    'work_type_slug' => 'maintenance_request',
                    'assignee_id' => $officer->id,
                    'due_at' => now()->addWeek()->toIso8601String(),
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/meetings/{$meetingId}")
            ->assertOk()
            ->assertJsonPath('action_items.0.title', 'Replace ward signage');
    }
}
