<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
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
