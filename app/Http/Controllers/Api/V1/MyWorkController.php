<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityEventResource;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\ActivityEvent;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
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

        $openForMe = WorkItem::query()
            ->visibleTo($user)
            ->where(function (Builder $q) use ($user): void {
                $q->where('assignee_id', $user->id)
                    ->orWhere('requester_id', $user->id);
            })
            ->whereHas('status', fn (Builder $q) => $q->where('is_terminal', false));

        $overdue = (clone $openForMe)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $dueToday = (clone $openForMe)
            ->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $highPriority = (clone $openForMe)
            ->whereIn('priority', ['high', 'urgent'])
            ->count();

        $statusRows = (clone $openForMe)
            ->join('workflow_statuses', 'workflow_statuses.id', '=', 'work_items.workflow_status_id')
            ->selectRaw('workflow_statuses.category as category, COUNT(*) as total')
            ->groupBy('workflow_statuses.category')
            ->pluck('total', 'category');

        $priorityRows = (clone $openForMe)
            ->selectRaw('priority, COUNT(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority');

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

        $recentActivity = ActivityEvent::query()
            ->where('subject_type', (new WorkItem)->getMorphClass())
            ->whereIn('subject_id', WorkItem::query()
                ->where(function (Builder $q) use ($user): void {
                    $q->where('assignee_id', $user->id)
                        ->orWhere('requester_id', $user->id);
                })
                ->select('id'))
            ->latest()
            ->limit(12)
            ->with('causer')
            ->get();

        return response()->json([
            'data' => [
                'counts' => [
                    'assigned_open' => $assigned,
                    'submitted' => $submitted,
                    'my_tasks' => (clone $openForMe)->count(),
                    'overdue' => $overdue,
                    'due_today' => $dueToday,
                    'high_priority' => $highPriority,
                ],
                'by_status' => [
                    ['key' => 'todo', 'label' => 'Assigned', 'value' => (int) ($statusRows['todo'] ?? 0)],
                    ['key' => 'in_progress', 'label' => 'In Progress', 'value' => (int) ($statusRows['in_progress'] ?? 0)],
                    ['key' => 'done', 'label' => 'Completed', 'value' => 0],
                ],
                'by_priority' => [
                    ['key' => 'urgent', 'label' => 'Urgent', 'value' => (int) ($priorityRows['urgent'] ?? 0)],
                    ['key' => 'high', 'label' => 'High', 'value' => (int) ($priorityRows['high'] ?? 0)],
                    ['key' => 'normal', 'label' => 'Medium', 'value' => (int) ($priorityRows['normal'] ?? 0)],
                    ['key' => 'low', 'label' => 'Low', 'value' => (int) ($priorityRows['low'] ?? 0)],
                ],
                'recent' => WorkItemResource::collection($recent),
                'recent_activity' => ActivityEventResource::collection($recentActivity),
            ],
        ]);
    }
}
