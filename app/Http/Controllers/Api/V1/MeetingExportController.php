<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingExportController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting): StreamedResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $items = $meeting->workItems()
            ->with(['status', 'assignee', 'workType'])
            ->orderBy('reference_number')
            ->get();

        $filename = sprintf('meeting-%d-actions-%s.csv', $meeting->id, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($items, $meeting): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'meeting',
                'held_at',
                'reference_number',
                'title',
                'work_type',
                'status',
                'assignee',
                'due_at',
                'created_at',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $meeting->title,
                    $meeting->held_at?->toIso8601String(),
                    $item->reference_number,
                    $item->title,
                    $item->workType?->name,
                    $item->status?->label,
                    $item->assignee?->name,
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
