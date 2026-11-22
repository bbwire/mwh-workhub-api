<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkflowStatus;
use App\Services\WorkItemTransitionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBoardController extends Controller
{
    public function __invoke(
        Request $request,
        Project $project,
        WorkItemTransitionCatalog $transitions,
    ): JsonResponse {
        $this->authorize('view', $project);

        $statuses = WorkflowStatus::query()
            ->whereHas('workflow', fn ($q) => $q->where('is_default', true)
                ->whereHas('workType', fn ($typeQuery) => $typeQuery->where('slug', 'project_task')))
            ->orderBy('sort_order')
            ->get();

        $items = WorkItem::query()
            ->where('project_id', $project->id)
            ->visibleTo($request->user())
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee', 'project'])
            ->get();

        $items = $transitions->attachAvailable($items, $request->user())->groupBy('workflow_status_id');

        $columns = $statuses->map(function (WorkflowStatus $status) use ($items) {
            $columnItems = $items->get($status->id, collect());

            return [
                'status' => [
                    'id' => $status->id,
                    'slug' => $status->slug,
                    'label' => $status->label,
                    'category' => $status->category,
                    'is_terminal' => (bool) $status->is_terminal,
                ],
                'items' => WorkItemResource::collection($columnItems)->resolve(),
            ];
        })->filter(function (array $column) {
            return count($column['items']) > 0 || ! $column['status']['is_terminal'];
        })->values();

        return response()->json([
            'data' => [
                'project' => [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'name' => $project->name,
                ],
                'columns' => $columns,
            ],
        ]);
    }
}
