<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\MyWorkQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyWorkExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $items = MyWorkQuery::openFor($user)
            ->with(['status', 'assignee', 'requester', 'workType', 'workspace'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('reference_number')
            ->get();

        $filename = sprintf('my-work-%s.csv', now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($items, $user): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'reference_number',
                'title',
                'status',
                'priority',
                'role',
                'assignee',
                'requester',
                'work_type',
                'due_at',
                'created_at',
            ]);

            foreach ($items as $item) {
                $roles = [];
                if ($item->assignee_id === $user->id) {
                    $roles[] = 'assignee';
                }
                if ($item->requester_id === $user->id) {
                    $roles[] = 'requester';
                }

                fputcsv($handle, [
                    $item->reference_number,
                    $item->title,
                    $item->status?->label,
                    $item->priority?->value ?? $item->priority,
                    implode('+', $roles) ?: 'involved',
                    $item->assignee?->name,
                    $item->requester?->name,
                    $item->workType?->name,
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
