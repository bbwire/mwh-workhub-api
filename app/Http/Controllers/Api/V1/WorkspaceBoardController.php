<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use App\Models\Workspace;
use App\Models\WorkflowStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceBoardController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): JsonResponse
    {
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
            ->get()
            ->groupBy('workflow_status_id');

        $columns = $statuses->map(function (WorkflowStatus $status) use ($items) {
            $columnItems = $items->get($status->id, collect());

            return [
                'status' => [
                    'id' => $status->id,
                    'slug' => $status->slug,
                    'label' => $status->label,
                    'category' => $status->category,
                ],
                'items' => WorkItemResource::collection($columnItems)->resolve(),
            ];
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
