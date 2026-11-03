<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemDueSoonNotification;
use App\Support\DailyWorkItemNotificationDeduper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkItemDueSoonNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(DailyWorkItemNotificationDeduper $deduper): void
    {
        $windowStart = now();
        $windowEnd = now()->addDay();

        WorkItem::query()
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$windowStart, $windowEnd])
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->with('assignee')
            ->chunkById(100, function ($items) use ($deduper): void {
                foreach ($items as $workItem) {
                    /** @var User|null $assignee */
                    $assignee = $workItem->assignee;
                    if ($assignee === null || ! $assignee->is_active) {
                        continue;
                    }

                    if ($deduper->alreadySent($assignee, 'work_item.due_soon', $workItem->id)) {
                        continue;
                    }

                    $assignee->notify(new WorkItemDueSoonNotification($workItem));
                    $deduper->markSent($assignee, 'work_item.due_soon', $workItem->id);
                }
            });
    }
}
