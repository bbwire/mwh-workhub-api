<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use App\Models\Workspace;
use App\Models\WorkflowStatus;
use App\Services\WorkItemTransitionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceBoardController extends Controller
{
    public function __invoke(
        Request $request,
        Workspace $workspace,
        WorkItemTransitionCatalog $transitions,
    ): JsonResponse {
        $user = $request->user();

        if (! $user->hasRole('system_admin') && ! $user->can('dashboard.management')) {
            abort_unless(
                $user->workspaces()->where('workspaces.id', $workspace->id)->exists()
                    || ($workspace->department_id && $user->supervisedDepartmentIds()->contains($workspace->department_id)),
                403
            );
        }

        $workflowIds = $workspace->workTypes()->with('defaultWorkflow')->get()
            ->pluck('defaultWorkflow.id')
            ->filter()
            ->unique();

        $statuses = WorkflowStatus::query()
            ->whereIn('workflow_id', $workflowIds)
            ->orderBy('sort_order')
            ->get();

        $items = WorkItem::query()
            ->visibleTo($user)
            ->where('workspace_id', $workspace->id)
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->get();

        $items = $transitions->attachAvailable($items, $user)->groupBy('workflow_status_id');

        // Shared hospital board columns — same on Tasks and department pages.
        $canonical = ['submitted', 'triaged', 'assigned', 'in_progress', 'pending_parts', 'resolved'];
        $usesHospitalBoard = $statuses->contains(fn (WorkflowStatus $status) => in_array($status->slug, $canonical, true));

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
        })->filter(function (array $column) use ($canonical, $usesHospitalBoard) {
            $slug = $column['status']['slug'];
            $hasItems = count($column['items']) > 0;

            if ($usesHospitalBoard) {
                return in_array($slug, $canonical, true) || $hasItems;
            }

            // Project boards keep active columns; hide empty terminals.
            return $hasItems || ! $column['status']['is_terminal'];
        })->values();

        return response()->json([
            'data' => [
                'workspace' => [
                    'id' => $workspace->id,
                    'slug' => $workspace->slug,
                    'name' => $workspace->name,
                ],
                'columns' => $columns,
            ],
        ]);
    }
}
