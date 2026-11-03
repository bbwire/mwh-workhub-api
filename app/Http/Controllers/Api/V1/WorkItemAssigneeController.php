<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssigneeResource;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemAssigneeController extends Controller
{
    public function index(Request $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('assign', $workItem);

        $workItem->loadMissing('workspace');

        $assignees = $workItem->workspace
            ->members()
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'data' => AssigneeResource::collection($assignees),
        ]);
    }
}
