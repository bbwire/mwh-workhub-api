<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Department;
use App\Services\WorkItemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentDashboardController extends Controller
{
    public function __invoke(Request $request, Department $department, WorkItemMetricsService $metrics): JsonResponse
    {
        abort_unless($request->user()?->can('dashboard.department'), 403);
        abort_unless($metrics->userCanAccessDepartment($request->user(), $department->id), 403);

        $period = $metrics->resolvePeriod(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $base = $metrics->scopedQuery($request->user(), $department->id);

        $openItems = (clone $base)
            ->whereHas('status', fn ($q) => $q->where('is_terminal', false))
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'department' => [
                    'id' => $department->id,
                    'slug' => $department->slug,
                    'name' => $department->name,
                ],
                'period' => [
                    'from' => $period['from'],
                    'to' => $period['to'],
                ],
                'counts' => $metrics->countSnapshot($base, $period['from_carbon'], $period['to_carbon']),
                'open_items' => WorkItemResource::collection($openItems)->resolve(),
            ],
        ]);
    }
}
