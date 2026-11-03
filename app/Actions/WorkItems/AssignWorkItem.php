<?php

namespace App\Actions\WorkItems;

use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;
use App\Notifications\WorkItemAssignedNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class AssignWorkItem
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function execute(User $actor, WorkItem $workItem, ?User $assignee, ?string $reason = null): WorkItem
    {
        return DB::transaction(function () use ($actor, $workItem, $assignee, $reason): WorkItem {
            $workItem->assignee_id = $assignee?->id;
            $workItem->save();

            WorkItemAssignment::query()->create([
                'work_item_id' => $workItem->id,
                'assignee_id' => $assignee?->id,
                'assigned_by_id' => $actor->id,
                'reason' => $reason,
            ]);

            $this->activity->log($workItem, 'work_item.assigned', $actor, [
                'assignee_id' => $assignee?->id,
                'assignee_name' => $assignee?->name,
                'reason' => $reason,
            ]);

            if ($assignee !== null && $assignee->id !== $actor->id) {
                $assignee->notify(new WorkItemAssignedNotification(
                    $workItem->fresh(['workType', 'workspace', 'status']),
                    $actor->name,
                ));
            }

            return $workItem->fresh(['workType', 'workspace', 'status', 'requester', 'assignee']);
        });
    }
}
