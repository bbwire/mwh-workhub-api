<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectExportController extends Controller
{
    public function __invoke(Request $request, Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        $items = $project->workItems()
            ->visibleTo($request->user())
            ->with(['status', 'assignee', 'workType'])
            ->orderBy('reference_number')
            ->get();

        $filename = sprintf('project-%s-tasks-%s.csv', $project->slug, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($items, $project): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'project',
                'reference_number',
                'title',
                'priority',
                'status',
                'assignee',
                'due_at',
                'created_at',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $project->name,
                    $item->reference_number,
                    $item->title,
                    $item->priority?->value,
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
