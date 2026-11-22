<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogQuery;
use App\Support\ActivitySummary;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActivityLogPdfController extends Controller
{
    public function __invoke(Request $request, ActivityLogQuery $query): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $events = $query->visibleTo($user)->limit(2_000)->get();

        $rows = $events->map(fn ($event) => [
            'when' => $event->created_at?->format('Y-m-d H:i') ?? '',
            'who' => $event->causer?->name ?? 'System',
            'action' => ActivitySummary::describe($event),
            'reference' => $event->subject?->reference_number ?? '—',
            'task' => $event->subject?->title ?? '—',
        ]);

        $html = view('reports.audit-log-pdf', [
            'rows' => $rows,
            'total' => $rows->count(),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('audit-log-%s.pdf', now()->format('Y-m-d'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
