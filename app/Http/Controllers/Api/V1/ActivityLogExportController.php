<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogQuery;
use App\Support\ActivitySummary;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogExportController extends Controller
{
    public function __invoke(Request $request, ActivityLogQuery $query): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $events = $query->visibleTo($user)->limit(10_000)->get();

        $filename = sprintf('audit-log-%s.csv', now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($events): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'when',
                'who',
                'action',
                'reference',
                'task',
                'event',
            ]);

            foreach ($events as $event) {
                fputcsv($handle, [
                    $event->created_at?->toIso8601String(),
                    $event->causer?->name ?? 'System',
                    ActivitySummary::describe($event),
                    $event->subject?->reference_number ?? '',
                    $event->subject?->title ?? '',
                    $event->event,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
