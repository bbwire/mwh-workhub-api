<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminWorkTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_create_update_and_delete_work_type(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $workspaceId = WorkType::query()->where('slug', 'maintenance_request')->value('workspace_id');

        $this->actingAs($admin);

        $created = $this->postJson('/api/v1/admin/work-types', [
            'name' => 'Biomedical Request',
            'reference_prefix' => 'BIO',
            'workspace_id' => $workspaceId,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Biomedical Request')
            ->assertJsonPath('data.reference_prefix', 'BIO')
            ->assertJsonPath('data.slug', 'biomedical_request');

        $workTypeId = $created->json('data.id');

        $this->assertTrue(
            Workflow::query()
                ->where('work_type_id', $workTypeId)
                ->where('is_default', true)
                ->whereHas('statuses', fn ($q) => $q->where('slug', 'assigned'))
                ->exists()
        );

        $this->patchJson("/api/v1/admin/work-types/{$workTypeId}", [
            'name' => 'Biomedical Support',
            'reference_prefix' => 'BMS',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Biomedical Support')
            ->assertJsonPath('data.reference_prefix', 'BMS')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/admin/work-types/{$workTypeId}")
            ->assertOk();

        $this->assertDatabaseMissing('work_types', ['id' => $workTypeId]);
    }

    public function test_admin_cannot_delete_work_type_with_tasks(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $type = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();

        $this->actingAs($admin);

        $this->deleteJson("/api/v1/admin/work-types/{$type->id}")
            ->assertStatus(422);
    }

    public function test_admin_can_update_work_type_form_schema(): void
    {
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $type = WorkType::query()->where('slug', 'maintenance_request')->firstOrFail();

        $this->actingAs($admin);

        $this->patchJson("/api/v1/admin/work-types/{$type->id}", [
            'form_schema' => [
                'fields' => [
                    ['key' => 'location', 'label' => 'Location', 'required' => true],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.form_schema.fields.0.key', 'location');
    }
}
