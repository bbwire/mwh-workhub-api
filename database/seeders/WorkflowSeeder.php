<?php

namespace Database\Seeders;

use App\Models\WorkType;
use App\Models\Workflow;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $facilities = Workspace::query()->where('slug', 'facilities')->firstOrFail();
        $ict = Workspace::query()->where('slug', 'ict')->firstOrFail();
        $administration = Workspace::query()->where('slug', 'administration')->firstOrFail();

        $this->seedMaintenance($facilities);
        $this->seedIct($ict);
        $this->seedProjectTask($administration);
    }

    private function seedProjectTask(Workspace $workspace): void
    {
        $workType = WorkType::query()->updateOrCreate(
            ['slug' => 'project_task'],
            [
                'name' => 'Project Task',
                'reference_prefix' => 'PRJ',
                'workspace_id' => $workspace->id,
                'is_active' => true,
            ],
        );

        $workflow = Workflow::query()->updateOrCreate(
            ['work_type_id' => $workType->id, 'is_default' => true],
            ['name' => 'Project task default'],
        );

        $statuses = $this->upsertStatuses($workflow, [
            ['slug' => 'backlog', 'label' => 'Backlog', 'sort_order' => 10, 'category' => 'todo'],
            ['slug' => 'in_progress', 'label' => 'In progress', 'sort_order' => 20, 'category' => 'in_progress'],
            ['slug' => 'review', 'label' => 'Review', 'sort_order' => 30, 'category' => 'in_progress'],
            ['slug' => 'done', 'label' => 'Done', 'sort_order' => 40, 'category' => 'done', 'is_terminal' => true],
        ]);

        $this->upsertTransitions($workflow, $statuses, [
            ['backlog', 'in_progress', 'start', 'Start'],
            ['in_progress', 'review', 'submit_review', 'Submit for review'],
            ['review', 'done', 'complete', 'Complete'],
            ['review', 'in_progress', 'rework', 'Rework'],
        ]);
    }

    private function seedMaintenance(Workspace $workspace): void
    {
        $workType = WorkType::query()->updateOrCreate(
            ['slug' => 'maintenance_request'],
            [
                'name' => 'Maintenance Request',
                'reference_prefix' => 'MNT',
                'workspace_id' => $workspace->id,
                'is_active' => true,
            ],
        );

        $workflow = Workflow::query()->updateOrCreate(
            ['work_type_id' => $workType->id, 'is_default' => true],
            ['name' => 'Maintenance default'],
        );

        $statuses = $this->upsertStatuses($workflow, [
            ['slug' => 'submitted', 'label' => 'Submitted', 'sort_order' => 10, 'category' => 'todo'],
            ['slug' => 'triaged', 'label' => 'Triaged', 'sort_order' => 20, 'category' => 'todo'],
            ['slug' => 'assigned', 'label' => 'Assigned', 'sort_order' => 30, 'category' => 'todo'],
            ['slug' => 'in_progress', 'label' => 'In progress', 'sort_order' => 40, 'category' => 'in_progress'],
            ['slug' => 'pending_parts', 'label' => 'Pending parts', 'sort_order' => 50, 'category' => 'in_progress'],
            ['slug' => 'resolved', 'label' => 'Resolved', 'sort_order' => 60, 'category' => 'done'],
            ['slug' => 'closed', 'label' => 'Closed', 'sort_order' => 70, 'category' => 'done', 'is_terminal' => true],
            ['slug' => 'cancelled', 'label' => 'Cancelled', 'sort_order' => 80, 'category' => 'done', 'is_terminal' => true],
            ['slug' => 'reopened', 'label' => 'Reopened', 'sort_order' => 90, 'category' => 'in_progress'],
        ]);

        $this->upsertTransitions($workflow, $statuses, [
            ['submitted', 'triaged', 'triage', 'Triage'],
            ['triaged', 'assigned', 'assign', 'Assign', 'work.assign'],
            ['assigned', 'in_progress', 'start_progress', 'Start progress'],
            ['in_progress', 'pending_parts', 'pending_parts', 'Pending parts'],
            ['pending_parts', 'in_progress', 'resume_progress', 'Resume progress'],
            ['in_progress', 'resolved', 'resolve', 'Resolve'],
            ['resolved', 'closed', 'close', 'Close'],
            ['resolved', 'reopened', 'reopen', 'Reopen', null, true],
            ['submitted', 'cancelled', 'cancel', 'Cancel'],
        ]);
    }

    private function seedIct(Workspace $workspace): void
    {
        $workType = WorkType::query()->updateOrCreate(
            ['slug' => 'ict_support'],
            [
                'name' => 'ICT Support',
                'reference_prefix' => 'ICT',
                'workspace_id' => $workspace->id,
                'is_active' => true,
            ],
        );

        $workflow = Workflow::query()->updateOrCreate(
            ['work_type_id' => $workType->id, 'is_default' => true],
            ['name' => 'ICT support default'],
        );

        $statuses = $this->upsertStatuses($workflow, [
            ['slug' => 'new', 'label' => 'New', 'sort_order' => 10, 'category' => 'todo'],
            ['slug' => 'assigned', 'label' => 'Assigned', 'sort_order' => 20, 'category' => 'todo'],
            ['slug' => 'in_progress', 'label' => 'In progress', 'sort_order' => 30, 'category' => 'in_progress'],
            ['slug' => 'waiting_on_user', 'label' => 'Waiting on user', 'sort_order' => 40, 'category' => 'in_progress'],
            ['slug' => 'resolved', 'label' => 'Resolved', 'sort_order' => 50, 'category' => 'done'],
            ['slug' => 'closed', 'label' => 'Closed', 'sort_order' => 60, 'category' => 'done', 'is_terminal' => true],
        ]);

        $this->upsertTransitions($workflow, $statuses, [
            ['new', 'assigned', 'assign', 'Assign', 'work.assign'],
            ['assigned', 'in_progress', 'start_progress', 'Start progress'],
            ['in_progress', 'waiting_on_user', 'wait_for_user', 'Waiting on user'],
            ['waiting_on_user', 'in_progress', 'resume_progress', 'Resume progress'],
            ['in_progress', 'resolved', 'resolve', 'Resolve'],
            ['resolved', 'closed', 'close', 'Close'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<string, WorkflowStatus>
     */
    private function upsertStatuses(Workflow $workflow, array $definitions): array
    {
        $map = [];

        foreach ($definitions as $definition) {
            $status = WorkflowStatus::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'slug' => $definition['slug'],
                ],
                [
                    'label' => $definition['label'],
                    'sort_order' => $definition['sort_order'],
                    'category' => $definition['category'],
                    'is_terminal' => Arr::get($definition, 'is_terminal', false),
                ],
            );

            $map[$definition['slug']] = $status;
        }

        return $map;
    }

    /**
     * @param  array<string, WorkflowStatus>  $statuses
     * @param  array<int, array<int, mixed>>  $definitions
     */
    private function upsertTransitions(Workflow $workflow, array $statuses, array $definitions): void
    {
        foreach ($definitions as $definition) {
            [$from, $to, $slug, $label, $permission, $requiresComment] = array_pad($definition, 6, null);

            WorkflowTransition::query()->updateOrCreate(
                [
                    'workflow_id' => $workflow->id,
                    'slug' => $slug,
                ],
                [
                    'from_status_id' => $statuses[$from]->id,
                    'to_status_id' => $statuses[$to]->id,
                    'label' => $label,
                    'required_permission' => $permission,
                    'requires_comment' => (bool) $requiresComment,
                ],
            );
        }
    }
}
