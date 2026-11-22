<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkflowTransition;
use Illuminate\Support\Collection;

class WorkItemTransitionCatalog
{
    /**
     * @param  Collection<int, WorkItem>  $workItems
     * @return Collection<int, WorkItem>
     */
    public function attachAvailable(Collection $workItems, ?User $user): Collection
    {
        if ($workItems->isEmpty()) {
            return $workItems;
        }

        $fromStatusIds = $workItems
            ->pluck('workflow_status_id')
            ->filter()
            ->unique()
            ->values();

        $byFromStatus = WorkflowTransition::query()
            ->whereIn('from_status_id', $fromStatusIds)
            ->with('toStatus')
            ->orderBy('slug')
            ->get()
            ->groupBy('from_status_id');

        return $workItems->map(function (WorkItem $workItem) use ($byFromStatus, $user): WorkItem {
            $workItem->setAttribute(
                'available_transitions',
                $this->mapTransitions($byFromStatus->get($workItem->workflow_status_id, collect()), $user),
            );

            return $workItem;
        });
    }

    public function forWorkItem(WorkItem $workItem, ?User $user): WorkItem
    {
        $transitions = WorkflowTransition::query()
            ->where('from_status_id', $workItem->workflow_status_id)
            ->with('toStatus')
            ->orderBy('slug')
            ->get();

        $workItem->setAttribute('available_transitions', $this->mapTransitions($transitions, $user));

        return $workItem;
    }

    /**
     * @param  Collection<int, WorkflowTransition>  $transitions
     * @return array<int, array{slug: string, label: string, requires_comment: bool, to_status_id: int, to_status_slug: string, to_status_label: string}>
     */
    private function mapTransitions(Collection $transitions, ?User $user): array
    {
        return $transitions
            ->filter(function (WorkflowTransition $transition) use ($user): bool {
                if ($transition->required_permission === null) {
                    return true;
                }

                return $user?->can($transition->required_permission) ?? false;
            })
            ->map(fn (WorkflowTransition $transition): array => [
                'slug' => $transition->slug,
                'label' => $transition->label,
                'requires_comment' => $transition->requires_comment,
                'to_status_id' => $transition->toStatus->id,
                'to_status_slug' => $transition->toStatus->slug,
                'to_status_label' => $transition->toStatus->label,
            ])
            ->values()
            ->all();
    }
}
