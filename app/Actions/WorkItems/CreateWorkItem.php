<?php

namespace App\Actions\WorkItems;

use App\Enums\WorkItemPriority;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\Workflow;
use App\Models\WorkType;
use App\Services\ActivityLogger;
use App\Services\ReferenceNumberGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateWorkItem
{
    public function __construct(
        private readonly ReferenceNumberGenerator $referenceNumbers,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $requester, WorkType $workType, array $data): WorkItem
    {
        return DB::transaction(function () use ($requester, $workType, $data): WorkItem {
            $workflow = Workflow::query()
                ->where('work_type_id', $workType->id)
                ->where('is_default', true)
                ->with('statuses')
                ->firstOrFail();
            $initialStatus = $workflow->statuses->sortBy('sort_order')->firstOrFail();

            $customFields = $data['custom_fields'] ?? [];
            $priority = WorkItemPriority::tryFrom(Arr::get($data, 'priority', 'normal'))
                ?? WorkItemPriority::Normal;

            if (Arr::get($customFields, 'safety_hazard') === true) {
                $priority = WorkItemPriority::Urgent;
            }

            $workItem = WorkItem::query()->create([
                'reference_number' => $this->referenceNumbers->next($workType),
                'work_type_id' => $workType->id,
                'workspace_id' => $workType->workspace_id,
                'project_id' => Arr::get($data, 'project_id'),
                'workflow_status_id' => $initialStatus->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $priority,
                'requester_id' => $requester->id,
                'assignee_id' => Arr::get($data, 'assignee_id'),
                'department_id' => $requester->primary_department_id,
                'due_at' => Arr::get($data, 'due_at'),
                'custom_fields' => $customFields,
                'confidential' => (bool) Arr::get($data, 'confidential', false),
            ]);

            $this->activity->log($workItem, 'work_item.created', $requester, [
                'reference_number' => $workItem->reference_number,
                'work_type' => $workType->slug,
            ]);

            return $workItem->fresh([
                'workType',
                'workspace',
                'status',
                'requester',
                'assignee',
            ]);
        });
    }
}
