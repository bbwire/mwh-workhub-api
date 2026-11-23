<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\MyWorkQuery;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MyWorkPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $items = MyWorkQuery::openFor($user)
            ->with(['status', 'assignee', 'requester', 'workType'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('reference_number')
            ->get();

        $overdue = $items->filter(fn ($item) => $item->due_at !== null && $item->due_at->isPast())->count();
        $dueToday = $items->filter(fn ($item) => $item->due_at !== null && $item->due_at->isToday())->count();

        $html = view('reports.my-work-pdf', [
            'user' => $user,
            'items' => $items,
            'counts' => [
                'open' => $items->count(),
                'overdue' => $overdue,
                'due_today' => $dueToday,
            ],
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = sprintf('my-work-%s.pdf', now()->format('Y-m-d'));

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
