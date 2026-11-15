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
use Illuminate\Validation\ValidationException;

class CreateWorkItem
{
    public function __construct(
        private readonly ReferenceNumberGenerator $referenceNumbers,
        private readonly ActivityLogger $activity,
        private readonly AssignWorkItem $assignWorkItem,
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

            $workspace = $workType->workspace()->firstOrFail();
            $departmentId = Arr::get($data, 'department_id');
            if ($departmentId !== null && (int) $workspace->department_id !== (int) $departmentId) {
                throw ValidationException::withMessages([
                    'department_id' => 'Choose the department that owns this kind of work.',
                ]);
            }

            $assignee = null;
            if (Arr::get($data, 'assignee_id')) {
                $assignee = User::query()
                    ->whereKey($data['assignee_id'])
                    ->where('is_active', true)
                    ->first();
                $isMember = $assignee !== null && $workspace->members()->whereKey($assignee->id)->exists();
                if (! $isMember) {
                    throw ValidationException::withMessages([
                        'assignee_id' => 'Choose someone who works in that department.',
                    ]);
                }
            }

            $status = $initialStatus;
            if ($assignee !== null) {
                $assigned = $workflow->statuses->firstWhere('slug', 'assigned');
                if ($assigned !== null) {
                    $status = $assigned;
                }
            }

            $workItem = WorkItem::query()->create([
                'reference_number' => $this->referenceNumbers->next($workType),
                'work_type_id' => $workType->id,
                'workspace_id' => $workType->workspace_id,
                'project_id' => Arr::get($data, 'project_id'),
                'workflow_status_id' => $status->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $priority,
                'requester_id' => $requester->id,
                'assignee_id' => null,
                'department_id' => $departmentId ?? $requester->primary_department_id,
                'due_at' => Arr::get($data, 'due_at'),
                'custom_fields' => $customFields,
                'confidential' => (bool) Arr::get($data, 'confidential', false),
            ]);

            $this->activity->log($workItem, 'work_item.created', $requester, [
                'reference_number' => $workItem->reference_number,
                'work_type' => $workType->slug,
            ]);

            if ($assignee !== null) {
                $workItem = $this->assignWorkItem->execute($requester, $workItem, $assignee);
            }

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
