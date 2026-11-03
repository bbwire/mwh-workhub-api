<?php

namespace App\Actions\WorkItems;

use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkflowTransition;
use App\Notifications\WorkItemStatusChangedNotification;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyWorkItemTransition
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function execute(User $actor, WorkItem $workItem, string $transitionSlug, ?string $comment = null): WorkItem
    {
        return DB::transaction(function () use ($actor, $workItem, $transitionSlug, $comment): WorkItem {
            $workItem->loadMissing('status');

            $transition = WorkflowTransition::query()
                ->where('workflow_id', $workItem->status->workflow_id)
                ->where('from_status_id', $workItem->workflow_status_id)
                ->where('slug', $transitionSlug)
                ->with('toStatus')
                ->first();

            if ($transition === null) {
                throw ValidationException::withMessages([
                    'transition' => ['This transition is not allowed from the current status.'],
                ]);
            }

            if ($transition->required_permission && ! $actor->can($transition->required_permission)) {
                throw ValidationException::withMessages([
                    'transition' => ['You are not allowed to perform this transition.'],
                ]);
            }

            if ($transition->requires_comment && blank($comment)) {
                throw ValidationException::withMessages([
                    'comment' => ['A comment is required for this transition.'],
                ]);
            }

            $fromSlug = $workItem->status->slug;
            $fromLabel = $workItem->status->label;
            $toStatus = $transition->toStatus;

            $workItem->workflow_status_id = $toStatus->id;

            if (in_array($toStatus->slug, ['resolved', 'closed', 'completed'], true)) {
                $workItem->resolved_at = $workItem->resolved_at ?? now();
            }

            if ($toStatus->slug === 'closed' || ($toStatus->is_terminal && $toStatus->slug !== 'cancelled')) {
                $workItem->closed_at = now();
            }

            $workItem->save();

            $this->activity->log($workItem, 'work_item.transitioned', $actor, [
                'from' => $fromSlug,
                'to' => $toStatus->slug,
                'to_status_label' => $toStatus->label,
                'transition' => $transitionSlug,
                'comment' => $comment,
            ]);

            $workItem->load(['requester', 'assignee']);

            $recipients = collect([$workItem->requester, $workItem->assignee])
                ->filter()
                ->unique('id')
                ->reject(fn (User $user): bool => $user->id === $actor->id);

            foreach ($recipients as $recipient) {
                $recipient->notify(new WorkItemStatusChangedNotification(
                    $workItem,
                    $fromLabel,
                    $toStatus->label,
                    $actor->name,
                ));
            }

            return $workItem->fresh(['workType', 'workspace', 'status', 'requester', 'assignee']);
        });
    }
}
