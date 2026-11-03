<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\WorkItemMetricsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DepartmentActivityExportController extends Controller
{
    public function __invoke(Request $request, WorkItemMetricsService $metrics): StreamedResponse
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
            ->with(['status', 'assignee', 'requester'])
            ->orderBy('reference_number')
            ->get();

        $filename = sprintf(
            'department-activity-%s-%s.csv',
            $department->slug,
            now()->format('Y-m-d'),
        );

        return response()->streamDownload(function () use ($items, $department, $period): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'reference_number',
                'title',
                'status',
                'priority',
                'assignee',
                'requester',
                'due_at',
                'created_at',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->reference_number,
                    $item->title,
                    $item->status?->label,
                    $item->priority?->value,
                    $item->assignee?->name,
                    $item->requester?->name,
                    $item->due_at?->toIso8601String(),
                    $item->created_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
