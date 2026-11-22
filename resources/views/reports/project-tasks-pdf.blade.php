<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $project->name }} — Tasks</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f2744; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #0f2744; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #4b5563; }
        tr:nth-child(even) td { background: #f9fafb; }
        .empty { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    @include('reports.partials.organization-header')
    <h1>{{ $project->name }}</h1>
    <p class="meta">
        Project tasks
        @if ($project->department)
            · {{ $project->department->name }}
        @endif
        · Generated {{ now()->format('Y-m-d H:i') }}
    </p>

    @if ($milestones->isNotEmpty())
        <h2>Milestones</h2>
        <table>
            <thead>
                <tr>
                    <th>Milestone</th>
                    <th>Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($milestones as $milestone)
                    <tr>
                        <td>{{ $milestone->title }}</td>
                        <td>{{ $milestone->due_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $milestone->completed_at ? 'Completed' : 'Open' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Tasks</h2>
    @if ($items->isEmpty())
        <p class="empty">No tasks on this project.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Task</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->reference_number }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ ucfirst($item->priority?->value ?? (string) $item->priority) }}</td>
                        <td>{{ $item->status?->label ?? '—' }}</td>
                        <td>{{ $item->assignee?->name ?? 'Unassigned' }}</td>
                        <td>{{ $item->due_at?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('reports.partials.organization-footer')
</body>
</html>
