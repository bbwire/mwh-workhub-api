<?php

namespace App\Services;

use App\Models\Workflow;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use App\Models\WorkType;
use Illuminate\Support\Facades\DB;

class BootstrapDefaultWorkflow
{
    public function for(WorkType $workType): Workflow
    {
        return DB::transaction(function () use ($workType): Workflow {
            $workflow = Workflow::query()->updateOrCreate(
                [
                    'work_type_id' => $workType->id,
                    'is_default' => true,
                ],
                [
                    'name' => $workType->name.' default',
                ],
            );

            if ($workflow->statuses()->exists()) {
                return $workflow->load('statuses');
            }

            $definitions = [
                ['slug' => 'submitted', 'label' => 'Submitted', 'sort_order' => 10, 'category' => 'todo'],
                ['slug' => 'assigned', 'label' => 'Assigned', 'sort_order' => 20, 'category' => 'todo'],
                ['slug' => 'in_progress', 'label' => 'In progress', 'sort_order' => 30, 'category' => 'in_progress'],
                ['slug' => 'resolved', 'label' => 'Resolved', 'sort_order' => 40, 'category' => 'done'],
                ['slug' => 'closed', 'label' => 'Closed', 'sort_order' => 50, 'category' => 'done', 'is_terminal' => true],
                ['slug' => 'cancelled', 'label' => 'Cancelled', 'sort_order' => 60, 'category' => 'done', 'is_terminal' => true],
            ];

            $statuses = [];
            foreach ($definitions as $definition) {
                $statuses[$definition['slug']] = WorkflowStatus::query()->create([
                    'workflow_id' => $workflow->id,
                    'slug' => $definition['slug'],
                    'label' => $definition['label'],
                    'sort_order' => $definition['sort_order'],
                    'category' => $definition['category'],
                    'is_terminal' => $definition['is_terminal'] ?? false,
                ]);
            }

            $transitions = [
                ['submitted', 'assigned', 'assign', 'Assign', 'work.assign'],
                ['submitted', 'cancelled', 'cancel', 'Cancel'],
                ['assigned', 'in_progress', 'start_progress', 'Start progress'],
                ['assigned', 'submitted', 'return_to_submitted', 'Send back'],
                ['in_progress', 'resolved', 'resolve', 'Resolve'],
                ['in_progress', 'assigned', 'return_to_assigned', 'Send back'],
                ['resolved', 'closed', 'close', 'Close'],
                ['resolved', 'assigned', 'reopen', 'Reopen', null, true],
            ];

            foreach ($transitions as $transition) {
                [$from, $to, $slug, $label, $permission, $requiresComment] = array_pad($transition, 6, null);

                WorkflowTransition::query()->create([
                    'workflow_id' => $workflow->id,
                    'from_status_id' => $statuses[$from]->id,
                    'to_status_id' => $statuses[$to]->id,
                    'slug' => $slug,
                    'label' => $label,
                    'required_permission' => $permission,
                    'requires_comment' => (bool) $requiresComment,
                ]);
            }

            return $workflow->load('statuses');
        });
    }
}
