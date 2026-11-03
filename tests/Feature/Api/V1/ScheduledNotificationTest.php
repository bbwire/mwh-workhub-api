<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendWorkItemOverdueNotifications;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemOverdueNotification;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ScheduledNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        config(['queue.default' => 'sync']);
    }

    public function test_overdue_job_notifies_assignee_once_per_day(): void
    {
        Notification::fake();

        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Overdue test',
            'description' => 'Test',
            'custom_fields' => ['location' => 'Lab'],
        ])->json('data.id');

        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $this->actingAs($supervisor);
        $this->postJson("/api/v1/work-items/{$workItemId}/assign", [
            'assignee_id' => $officer->id,
        ])->assertOk();

        WorkItem::query()->whereKey($workItemId)->update([
            'due_at' => now()->subDay(),
        ]);

        Notification::fake();

        dispatch_sync(new SendWorkItemOverdueNotifications);

        Notification::assertSentTo($officer, WorkItemOverdueNotification::class);

        dispatch_sync(new SendWorkItemOverdueNotifications);

        Notification::assertSentToTimes($officer, WorkItemOverdueNotification::class, 1);
    }
}
