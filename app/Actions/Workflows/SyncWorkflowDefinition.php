<?php

namespace App\Actions\Workflows;

use App\Models\Workflow;
use App\Models\WorkflowStatus;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncWorkflowDefinition
{
    /**
     * @param  array<int, array<string, mixed>>  $statuses
     * @param  array<int, array<string, mixed>>  $transitions
     */
    public function execute(Workflow $workflow, array $statuses, array $transitions): Workflow
    {
        return DB::transaction(function () use ($workflow, $statuses, $transitions): Workflow {
            $incomingStatusSlugs = collect($statuses)->pluck('slug')->all();
            $existingStatuses = $workflow->statuses()->get()->keyBy('slug');

            foreach ($existingStatuses as $slug => $status) {
                if (in_array($slug, $incomingStatusSlugs, true)) {
                    continue;
                }

                if ($status->workItems()->exists()) {
                    throw ValidationException::withMessages([
                        'statuses' => ["Cannot remove status \"{$slug}\" while work items use it."],
                    ]);
                }

                $status->delete();
            }

            $statusIdBySlug = [];

            foreach ($statuses as $definition) {
                $status = WorkflowStatus::query()->updateOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'slug' => $definition['slug'],
                    ],
                    [
                        'label' => $definition['label'],
                        'sort_order' => $definition['sort_order'],
                        'category' => $definition['category'],
                        'is_terminal' => (bool) ($definition['is_terminal'] ?? false),
                    ],
                );

                $statusIdBySlug[$status->slug] = $status->id;
            }

            $incomingTransitionSlugs = collect($transitions)->pluck('slug')->all();

            $workflow->transitions()
                ->whereNotIn('slug', $incomingTransitionSlugs)
                ->delete();

            foreach ($transitions as $definition) {
                $fromSlug = $definition['from_status_slug'];
                $toSlug = $definition['to_status_slug'];

                if (! isset($statusIdBySlug[$fromSlug], $statusIdBySlug[$toSlug])) {
                    throw ValidationException::withMessages([
                        'transitions' => ["Transition \"{$definition['slug']}\" references unknown status slugs."],
                    ]);
                }

                WorkflowTransition::query()->updateOrCreate(
                    [
                        'workflow_id' => $workflow->id,
                        'slug' => $definition['slug'],
                    ],
                    [
                        'from_status_id' => $statusIdBySlug[$fromSlug],
                        'to_status_id' => $statusIdBySlug[$toSlug],
                        'label' => $definition['label'],
                        'required_permission' => $definition['required_permission'] ?? null,
                        'requires_comment' => (bool) ($definition['requires_comment'] ?? false),
                    ],
                );
            }

            return $workflow->fresh(['workType', 'statuses', 'transitions.fromStatus', 'transitions.toStatus']);
        });
    }
}
