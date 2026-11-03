<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_management_dashboard_requires_permission(): void
    {
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();
        $this->actingAs($staff);

        $this->getJson('/api/v1/dashboards/management')->assertForbidden();
    }

    public function test_hospital_management_can_load_dashboard(): void
    {
        $management = User::query()->where('email', 'management@mswnh.test')->firstOrFail();
        $this->actingAs($management);

        $this->getJson('/api/v1/dashboards/management')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'counts' => [
                        'total',
                        'awaiting_action',
                        'in_progress',
                        'completed',
                        'overdue',
                    ],
                    'by_department',
                ],
            ]);
    }

    public function test_department_report_matches_dashboard_counts(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $department = Department::query()->where('slug', 'facilities-maintenance')->firstOrFail();

        $this->actingAs($supervisor);

        $dashboardCounts = $this->getJson("/api/v1/dashboards/department/{$department->id}")
            ->assertOk()
            ->json('data.counts');

        $reportCounts = $this->getJson('/api/v1/reports/department-activity?department_id='.$department->id)
            ->assertOk()
            ->json('data.counts');

        $this->assertSame($dashboardCounts['total'], $reportCounts['total']);
        $this->assertSame($dashboardCounts['overdue'], $reportCounts['overdue']);
    }
}
