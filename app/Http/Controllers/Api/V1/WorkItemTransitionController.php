<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WorkItems\ApplyWorkItemTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TransitionWorkItemRequest;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;

class WorkItemTransitionController extends Controller
{
    public function store(
        TransitionWorkItemRequest $request,
        WorkItem $workItem,
        ApplyWorkItemTransition $applyTransition,
    ): JsonResponse {
        $workItem = $applyTransition->execute(
            $request->user(),
            $workItem,
            $request->string('transition')->toString(),
            $request->input('comment'),
        );

        return response()->json([
            'data' => WorkItemResource::make($workItem),
        ]);
    }
}
