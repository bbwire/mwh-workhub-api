<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityEventResource;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\ActivityEvent;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\WorkItem;
use App\Services\WorkItemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagementDashboardController extends Controller
{
    public function __invoke(Request $request, WorkItemMetricsService $metrics): JsonResponse
    {
        abort_unless($request->user()?->can('dashboard.management'), 403);

        $period = $metrics->resolvePeriod(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $base = $metrics->scopedQuery($request->user());

        $priorityItems = (clone $base)
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->whereIn('priority', ['urgent', 'high'])
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->latest()
            ->limit(10)
            ->get();

        $recentItems = (clone $base)
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->latest('updated_at')
            ->limit(10)
            ->get();

        $recentActivity = ActivityEvent::query()
            ->where('subject_type', (new WorkItem)->getMorphClass())
            ->latest()
            ->limit(15)
            ->with('causer')
            ->get();

        $from = $period['from_carbon'];
        $to = $period['to_carbon'];

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $period['from'],
                    'to' => $period['to'],
                ],
                'counts' => $metrics->countSnapshot($base, $from, $to),
                'by_department' => $metrics->departmentSummaries($base, $from, $to),
                'by_status' => $metrics->statusBreakdown($base, $from, $to),
                'by_priority' => $metrics->priorityBreakdown($base, $from, $to),
                'by_assignee' => $metrics->assigneeWorkload($base, $from, $to),
                'priority_items' => WorkItemResource::collection($priorityItems)->resolve(),
                'recent_items' => WorkItemResource::collection($recentItems)->resolve(),
                'recent_activity' => ActivityEventResource::collection($recentActivity)->resolve(),
                'accountability' => [
                    'meeting_actions_open' => WorkItem::query()
                        ->whereHas('meetings')
                        ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
                        ->count(),
                    'meeting_actions_overdue' => WorkItem::query()
                        ->whereHas('meetings')
                        ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
                        ->whereNotNull('due_at')
                        ->where('due_at', '<', now())
                        ->count(),
                    'meetings_this_period' => Meeting::query()
                        ->whereBetween('held_at', [$from, $to])
                        ->count(),
                    'projects_active' => Project::query()
                        ->where('status', ProjectStatus::Active)
                        ->count(),
                    'projects_with_overdue_tasks' => Project::query()
                        ->where('status', ProjectStatus::Active)
                        ->whereHas('workItems', fn ($q) => $q
                            ->whereHas('status', fn ($status) => $status->where('is_terminal', false))
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', now()))
                        ->count(),
                ],
            ],
        ]);
    }
}
