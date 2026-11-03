<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\AssignWorkItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AssignWorkItemRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;

class WorkItemAssignController extends Controller
{
    public function store(
        AssignWorkItemRequest $request,
        WorkItem $workItem,
        AssignWorkItem $assignWorkItem,
    ): JsonResponse {
        $this->authorize('assign', $workItem);

        $assignee = $request->filled('assignee_id')
            ? User::query()->findOrFail($request->integer('assignee_id'))
            : null;

        $workItem = $assignWorkItem->execute(
            $request->user(),
            $workItem,
            $assignee,
            $request->input('reason'),
        );

        return response()->json([
            'data' => WorkItemResource::make($workItem),
        ]);
    }
}
