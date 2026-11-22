<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\AssignWorkItem;
use App\Actions\WorkItems\CreateWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectTaskRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;

class ProjectTaskController extends Controller
{
    public function store(
        StoreProjectTaskRequest $request,
        Project $project,
        CreateWorkItem $createWorkItem,
        AssignWorkItem $assignWorkItem,
    ): JsonResponse {
        $workType = WorkType::query()
            ->where('slug', 'project_task')
            ->where('is_active', true)
            ->firstOrFail();

        $payload = $request->safe()->except(['assignee_id']);
        $payload['project_id'] = $project->id;

        // Administration workspace is hospital-wide — set department after create.
        $workItem = $createWorkItem->execute($request->user(), $workType, $payload);

        if ($project->department_id) {
            $workItem->forceFill(['department_id' => $project->department_id])->save();
        }

        if ($request->filled('assignee_id')) {
            $assignee = User::query()
                ->whereKey($request->integer('assignee_id'))
                ->where('is_active', true)
                ->first();

            if ($assignee !== null) {
                $workItem = $assignWorkItem->execute($request->user(), $workItem, $assignee);
            }
        }

        return response()->json([
            'data' => WorkItemResource::make($workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee', 'project'])),
        ], 201);
    }
}
