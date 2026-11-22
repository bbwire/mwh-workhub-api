<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_can_search_and_filter_tasks(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($user);

        $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Filter me uniquely',
            'description' => 'Corridor light out',
            'priority' => 'high',
            'due_at' => now()->startOfDay()->addHours(12)->toIso8601String(),
        ])->assertCreated();

        $this->getJson('/api/v1/work-items?q=Filter+me+uniquely')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Filter me uniquely');

        $this->getJson('/api/v1/work-items?priority=high,urgent&due=today&category=todo')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Filter me uniquely']);

        $summary = $this->getJson('/api/v1/my-work')->assertOk();
        $this->assertGreaterThanOrEqual(1, $summary->json('data.counts.my_tasks'));
        $this->assertGreaterThanOrEqual(1, $summary->json('data.counts.due_today'));
        $this->assertGreaterThanOrEqual(1, $summary->json('data.counts.high_priority'));
        $this->assertIsArray($summary->json('data.recent_activity'));
    }

    public function test_staff_can_file_a_request_with_a_department_and_assignee(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $ict = Department::query()->where('slug', 'ict')->firstOrFail();
        $officer = User::query()->where('email', 'ict.officer@mswnh.test')->firstOrFail();
        $this->actingAs($user);

        $this->getJson('/api/v1/departments?purpose=request')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'ict']);

        $this->getJson('/api/v1/workspaces/ict/members')
            ->assertOk()
            ->assertJsonFragment(['email' => $officer->email]);

        $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'ict_support',
            'title' => 'Ward clerk cannot sign in',
            'description' => 'The account is locked after the night shift.',
            'priority' => 'high',
            'department_id' => $ict->id,
            'assignee_id' => $officer->id,
        ])->assertCreated()
            ->assertJsonPath('data.department_id', $ict->id)
            ->assertJsonPath('data.assignee.id', $officer->id)
            ->assertJsonPath('data.status.slug', 'assigned');
    }

    public function test_staff_sees_only_their_departments(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($user);

        $this->getJson('/api/v1/departments')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'facilities-maintenance'])
            ->assertJsonMissing(['slug' => 'ict']);
    }

    public function test_staff_can_see_department_people_and_the_audit_log(): void
    {
        $user = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $department = Department::query()->where('slug', 'facilities-maintenance')->firstOrFail();
        $this->actingAs($user);

        $this->getJson('/api/v1/departments/'.$department->id)
            ->assertOk()
            ->assertJsonFragment(['email' => 'facilities.officer@mswnh.test']);

        $this->getJson('/api/v1/activity')
            ->assertOk()
            ->assertJsonFragment(['event' => 'work_item.created']);

        $this->get('/api/v1/activity/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->get('/api/v1/activity/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
