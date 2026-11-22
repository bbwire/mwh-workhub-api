<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\WorkItemMetricsService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DepartmentActivityPdfController extends Controller
{
    public function __invoke(Request $request, WorkItemMetricsService $metrics): Response
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
        $from = $period['from_carbon'];
        $to = $period['to_carbon'];

        $items = (clone $base)
            ->whereBetween('created_at', [$from, $to])
            ->with(['status', 'assignee', 'requester'])
            ->orderBy('reference_number')
            ->get();

        $counts = $metrics->countSnapshot($base, $from, $to);

        $periodLabel = $from->format('F Y');

        $html = view('reports.department-activity-pdf', [
            'department' => $department,
            'periodLabel' => $periodLabel,
            'counts' => $counts,
            'items' => $items,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf(
            'department-activity-%s-%s.pdf',
            $department->slug,
            now()->format('Y-m-d'),
        );

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
