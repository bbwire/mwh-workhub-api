<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_assign_creates_in_app_notification_for_assignee(): void
    {
        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Light bulb out',
            'description' => 'Corridor lighting.',
            'custom_fields' => ['location' => 'Ward 2'],
        ])->json('data.id');

        $this->actingAs($supervisor);
        $this->postJson("/api/v1/work-items/{$workItemId}/assign", [
            'assignee_id' => $officer->id,
            'reason' => 'Facilities queue',
        ])->assertOk();

        $this->actingAs($officer);

        $response = $this->getJson('/api/v1/notifications');
        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertSame('work_item.assigned', $response->json('data.0.data.type'));

        $notificationId = $response->json('data.0.id');

        $this->postJson("/api/v1/notifications/{$notificationId}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);
    }
}
