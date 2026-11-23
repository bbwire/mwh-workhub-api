<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\WorkItemOverdueNotification;
use App\Support\AdminNotifiableUsers;
use App\Support\DailyWorkItemNotificationDeduper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class SendWorkItemOverdueNotifications implements ShouldQueue
{
    use Queueable;

    public function handle(
        DailyWorkItemNotificationDeduper $deduper,
        AdminNotifiableUsers $admins,
    ): void {
        $adminUsers = $admins->all();

        WorkItem::query()
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->with('assignee')
            ->chunkById(100, function ($items) use ($deduper, $adminUsers): void {
                foreach ($items as $workItem) {
                    foreach ($this->recipientsFor($workItem, $adminUsers) as $recipient) {
                        if ($deduper->alreadySent($recipient, 'work_item.overdue', $workItem->id)) {
                            continue;
                        }

                        $recipient->notify(new WorkItemOverdueNotification($workItem));
                        $deduper->markSent($recipient, 'work_item.overdue', $workItem->id);
                    }
                }
            });
    }

    /**
     * @param  Collection<int, User>  $admins
     * @return Collection<int, User>
     */
    private function recipientsFor(WorkItem $workItem, Collection $admins): Collection
    {
        $recipients = collect();

        /** @var User|null $assignee */
        $assignee = $workItem->assignee;
        if ($assignee !== null && $assignee->is_active) {
            $recipients->push($assignee);
        }

        return $recipients
            ->merge($admins)
            ->unique('id')
            ->values();
    }
}
