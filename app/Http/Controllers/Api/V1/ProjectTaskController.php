<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\CreateWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectTaskRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Project;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;

class ProjectTaskController extends Controller
{
    public function store(StoreProjectTaskRequest $request, Project $project, CreateWorkItem $createWorkItem): JsonResponse
    {
        $workType = WorkType::query()
            ->where('slug', 'project_task')
            ->where('is_active', true)
            ->firstOrFail();

        $payload = array_merge($request->validated(), [
            'project_id' => $project->id,
        ]);

        $workItem = $createWorkItem->execute($request->user(), $workType, $payload);

        return response()->json([
            'data' => WorkItemResource::make($workItem->load(['workType', 'workspace', 'status', 'requester', 'assignee'])),
        ], 201);
    }
}
