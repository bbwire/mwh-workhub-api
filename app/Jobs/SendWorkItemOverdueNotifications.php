<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemOverdueNotification;
use App\Support\DailyWorkItemNotificationDeduper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWorkItemOverdueNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(DailyWorkItemNotificationDeduper $deduper): void
    {
        WorkItem::query()
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->with('assignee')
            ->chunkById(100, function ($items) use ($deduper): void {
                foreach ($items as $workItem) {
                    /** @var User|null $assignee */
                    $assignee = $workItem->assignee;
                    if ($assignee === null || ! $assignee->is_active) {
                        continue;
                    }

                    if ($deduper->alreadySent($assignee, 'work_item.overdue', $workItem->id)) {
                        continue;
                    }

                    $assignee->notify(new WorkItemOverdueNotification($workItem));
                    $deduper->markSent($assignee, 'work_item.overdue', $workItem->id);
                }
            });
    }
}
