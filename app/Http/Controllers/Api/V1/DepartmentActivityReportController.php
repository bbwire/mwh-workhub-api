<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkItemResource;
use App\Models\Department;
use App\Services\WorkItemMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentActivityReportController extends Controller
{
    public function __invoke(Request $request, WorkItemMetricsService $metrics): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->can('reports.department') || $user?->can('reports.hospital'), 403);

        $departmentId = $request->integer('department_id');
        abort_unless($departmentId > 0, 422, 'department_id is required.');

        $department = Department::query()->findOrFail($departmentId);

        if (! $user->can('reports.hospital') && ! $user->can('dashboard.management')) {
            abort_unless($metrics->userCanAccessDepartment($user, $department->id), 403);
        }

        $period = $metrics->resolvePeriod(
            $request->string('from')->toString() ?: null,
            $request->string('to')->toString() ?: null,
        );

        $base = $metrics->scopedQuery($user, $department->id);

        $items = (clone $base)
            ->whereBetween('created_at', [$period['from_carbon'], $period['to_carbon']])
            ->with(['workType', 'workspace', 'status', 'requester', 'assignee'])
            ->latest()
            ->get();

        return response()->json([
            'data' => [
                'report' => 'department-activity',
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
                'items' => WorkItemResource::collection($items)->resolve(),
            ],
        ]);
    }
}
