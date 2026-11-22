<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectPdfController extends Controller
{
    public function __invoke(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $items = $project->workItems()
            ->visibleTo($request->user())
            ->with(['status', 'assignee', 'workType'])
            ->orderBy('reference_number')
            ->get();

        $milestones = $project->milestones()->orderBy('sort_order')->get();

        $html = view('reports.project-tasks-pdf', [
            'project' => $project->loadMissing('department'),
            'items' => $items,
            'milestones' => $milestones,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('project-%s-tasks-%s.pdf', $project->slug, now()->format('Y-m-d'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
