<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssigneeResource;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemMentionableController extends Controller
{
    public function __invoke(Request $request, WorkItem $workItem): JsonResponse
    {
        $this->authorize('view', $workItem);

        $workItem->loadMissing('workspace');

        $memberIds = $workItem->workspace
            ? $workItem->workspace->members()->where('users.is_active', true)->pluck('users.id')
            : collect();

        $relatedIds = collect([
            $workItem->requester_id,
            $workItem->assignee_id,
            $request->user()?->id,
        ])->filter();

        $users = User::query()
            ->where('is_active', true)
            ->whereIn('id', $memberIds->merge($relatedIds)->unique()->filter())
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => AssigneeResource::collection($users),
        ]);
    }
}
