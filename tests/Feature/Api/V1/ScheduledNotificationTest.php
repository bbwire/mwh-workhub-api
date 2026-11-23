<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendWorkItemDueSoonNotifications;
use App\Jobs\SendWorkItemOverdueNotifications;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemDueSoonNotification;
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

    public function test_overdue_job_notifies_assignee_and_admin_once_per_day(): void
    {
        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
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

        $officer->notifications()->delete();
        $admin->notifications()->delete();

        dispatch_sync(new SendWorkItemOverdueNotifications);

        $this->assertSame(1, $this->overdueCountFor($officer, $workItemId));
        $this->assertSame(1, $this->overdueCountFor($admin, $workItemId));

        dispatch_sync(new SendWorkItemOverdueNotifications);

        $this->assertSame(1, $this->overdueCountFor($officer, $workItemId));
        $this->assertSame(1, $this->overdueCountFor($admin, $workItemId));
    }

    private function overdueCountFor(User $user, int $workItemId): int
    {
        return $user->notifications()
            ->where('data->type', 'work_item.overdue')
            ->where('data->work_item_id', $workItemId)
            ->count();
    }

    public function test_due_soon_job_notifies_assignee_and_admin(): void
    {
        Notification::fake();

        $officer = User::query()->where('email', 'facilities.officer@mswnh.test')->firstOrFail();
        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Due soon test',
            'description' => 'Test',
            'custom_fields' => ['location' => 'Lab'],
        ])->json('data.id');

        $supervisor = User::query()->where('email', 'facilities.supervisor@mswnh.test')->firstOrFail();
        $this->actingAs($supervisor);
        $this->postJson("/api/v1/work-items/{$workItemId}/assign", [
            'assignee_id' => $officer->id,
        ])->assertOk();

        WorkItem::query()->whereKey($workItemId)->update([
            'due_at' => now()->addHours(6),
        ]);

        Notification::fake();

        dispatch_sync(new SendWorkItemDueSoonNotifications);

        Notification::assertSentTo($officer, WorkItemDueSoonNotification::class);
        Notification::assertSentTo($admin, WorkItemDueSoonNotification::class);
    }

    public function test_overdue_job_notifies_admin_for_unassigned_items(): void
    {
        Notification::fake();

        $admin = User::query()->where('email', 'admin@mswnh.test')->firstOrFail();
        $staff = User::query()->where('email', 'staff@mswnh.test')->firstOrFail();

        $this->actingAs($staff);
        $workItemId = $this->postJson('/api/v1/work-items', [
            'work_type_slug' => 'maintenance_request',
            'title' => 'Unassigned overdue',
            'description' => 'Test',
            'custom_fields' => ['location' => 'Lab'],
        ])->json('data.id');

        WorkItem::query()->whereKey($workItemId)->update([
            'due_at' => now()->subDay(),
            'assignee_id' => null,
        ]);

        Notification::fake();

        dispatch_sync(new SendWorkItemOverdueNotifications);

        Notification::assertSentTo($admin, WorkItemOverdueNotification::class);
    }
}
