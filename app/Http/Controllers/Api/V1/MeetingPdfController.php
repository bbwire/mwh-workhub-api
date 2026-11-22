<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeetingPdfController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): Response
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $items = $meeting->workItems()
            ->with(['status', 'assignee', 'workType'])
            ->orderBy('reference_number')
            ->get();

        $html = view('reports.meeting-actions-pdf', [
            'meeting' => $meeting,
            'items' => $items,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('meeting-%d-actions-%s.pdf', $meeting->id, now()->format('Y-m-d'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
