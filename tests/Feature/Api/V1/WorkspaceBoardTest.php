<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_facilities_supervisor_can_load_board_columns(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $this->actingAs($supervisor);

        $this->getJson('/api/v1/workspaces/facilities/board')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'workspace' => ['slug', 'name'],
                    'columns' => [
                        ['status' => ['slug', 'label'], 'items'],
                    ],
                ],
            ]);
    }

    public function test_general_staff_cannot_load_facilities_board(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->getJson('/api/v1/workspaces/facilities/board')
            ->assertForbidden();
    }
}
