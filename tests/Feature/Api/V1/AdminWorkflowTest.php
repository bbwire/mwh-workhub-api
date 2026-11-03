<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_staff_cannot_list_workflows(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);

        $this->getJson('/api/v1/admin/workflows')->assertForbidden();
    }

    public function test_admin_can_view_and_update_workflow_definition(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $workType = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();
        $workflow = Workflow::query()->where('work_type_id', $workType->id)->where('is_default', true)->firstOrFail();

        $this->actingAs($admin);

        $this->getJson('/api/v1/admin/workflows')
            ->assertOk()
            ->assertJsonFragment(['id' => $workflow->id]);

        $this->getJson("/api/v1/admin/workflows/{$workflow->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'statuses',
                    'transitions',
                ],
            ]);

        $this->patchJson("/api/v1/admin/workflows/{$workflow->id}", [
            'name' => 'Maintenance (updated)',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Maintenance (updated)');

        $definition = $this->getJson("/api/v1/admin/workflows/{$workflow->id}")->json('data');

        $statuses = collect($definition['statuses'])->map(function (array $status): array {
            if ($status['slug'] === 'submitted') {
                $status['label'] = 'Received';
            }

            return $status;
        })->values()->all();

        $transitions = collect($definition['transitions'])->map(fn (array $transition): array => [
            'slug' => $transition['slug'],
            'from_status_slug' => $transition['from_status_slug'],
            'to_status_slug' => $transition['to_status_slug'],
            'label' => $transition['label'],
            'required_permission' => $transition['required_permission'],
            'requires_comment' => $transition['requires_comment'],
        ])->values()->all();

        $this->putJson("/api/v1/admin/workflows/{$workflow->id}/definition", [
            'statuses' => $statuses,
            'transitions' => $transitions,
        ])
            ->assertOk()
            ->assertJsonPath('data.statuses.0.label', 'Received');
    }

    public function test_cannot_remove_status_in_use(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $workType = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();
        $workflow = Workflow::query()->where('work_type_id', $workType->id)->where('is_default', true)->firstOrFail();

        $this->actingAs($staff);
        $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Blocked status removal',
            'description' => 'Test',
            'custom_fields' => ['location' => 'Ward'],
        ])->assertCreated();

        $definition = $this->actingAs($admin)
            ->getJson("/api/v1/admin/workflows/{$workflow->id}")
            ->json('data');

        $statuses = collect($definition['statuses'])
            ->reject(fn (array $status): bool => $status['slug'] === 'submitted')
            ->values()
            ->all();

        $this->putJson("/api/v1/admin/workflows/{$workflow->id}/definition", [
            'statuses' => $statuses,
            'transitions' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statuses']);
    }

    public function test_admin_can_add_transition_and_change_route(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $workType = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();
        $workflow = Workflow::query()->where('work_type_id', $workType->id)->where('is_default', true)->firstOrFail();

        $this->actingAs($admin);

        $definition = $this->getJson("/api/v1/admin/workflows/{$workflow->id}")->json('data');

        $statuses = collect($definition['statuses'])->map(fn (array $status): array => [
            'slug' => $status['slug'],
            'label' => $status['label'],
            'sort_order' => $status['sort_order'],
            'category' => $status['category'],
            'is_terminal' => $status['is_terminal'],
        ])->values()->all();

        $transitions = collect($definition['transitions'])->map(fn (array $transition): array => [
            'slug' => $transition['slug'],
            'from_status_slug' => $transition['from_status_slug'],
            'to_status_slug' => $transition['to_status_slug'],
            'label' => $transition['label'],
            'required_permission' => $transition['required_permission'],
            'requires_comment' => $transition['requires_comment'],
        ])->values()->all();

        $transitions[] = [
            'slug' => 'submitted_to_in_progress',
            'from_status_slug' => 'submitted',
            'to_status_slug' => 'in_progress',
            'label' => 'Fast track',
            'required_permission' => 'work.assign',
            'requires_comment' => true,
        ];

        $this->putJson("/api/v1/admin/workflows/{$workflow->id}/definition", [
            'statuses' => $statuses,
            'transitions' => $transitions,
        ])
            ->assertOk()
            ->assertJsonFragment([
                'slug' => 'submitted_to_in_progress',
                'from_status_slug' => 'submitted',
                'to_status_slug' => 'in_progress',
                'required_permission' => 'work.assign',
            ]);
    }

    public function test_transition_validation_rejects_unknown_status_slug(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $workType = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();
        $workflow = Workflow::query()->where('work_type_id', $workType->id)->where('is_default', true)->firstOrFail();

        $this->actingAs($admin);

        $definition = $this->getJson("/api/v1/admin/workflows/{$workflow->id}")->json('data');

        $statuses = collect($definition['statuses'])->map(fn (array $status): array => [
            'slug' => $status['slug'],
            'label' => $status['label'],
            'sort_order' => $status['sort_order'],
            'category' => $status['category'],
            'is_terminal' => $status['is_terminal'],
        ])->values()->all();

        $this->putJson("/api/v1/admin/workflows/{$workflow->id}/definition", [
            'statuses' => $statuses,
            'transitions' => [[
                'slug' => 'bad_link',
                'from_status_slug' => 'submitted',
                'to_status_slug' => 'nonexistent',
                'label' => 'Bad',
                'required_permission' => null,
                'requires_comment' => false,
            ]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transitions.0.to_status_slug']);
    }
}
