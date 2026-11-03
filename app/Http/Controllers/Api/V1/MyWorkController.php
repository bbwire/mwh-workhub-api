<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\WorkItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyWorkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $assigned = WorkItem::query()
            ->visibleTo($user)
            ->where('assignee_id', $user->id)
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->count();

        $submitted = WorkItem::query()
            ->where('requester_id', $user->id)
            ->count();

        $overdue = WorkItem::query()
            ->visibleTo($user)
            ->where('assignee_id', $user->id)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->count();

        $recent = WorkItem::query()
            ->visibleTo($user)
            ->where(function ($q) use ($user): void {
                $q->where('assignee_id', $user->id)
                    ->orWhere('requester_id', $user->id);
            })
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'counts' => [
                    'assigned_open' => $assigned,
                    'submitted' => $submitted,
                    'overdue' => $overdue,
                ],
                'recent' => WorkItemResource::collection($recent),
            ],
        ]);
    }
}
