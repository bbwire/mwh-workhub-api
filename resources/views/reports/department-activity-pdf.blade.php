<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $department->name }} — Department activity</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 24px;
        }
        h1 {
            font-size: 18px;
            margin: 0 0 4px;
            color: #0f2744;
        }
        .meta {
            color: #6b7280;
            margin-bottom: 16px;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            width: 25%;
        }
        .summary .label {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            margin-bottom: 2px;
        }
        .summary .value {
            font-size: 16px;
            font-weight: bold;
            color: #0f2744;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data th,
        table.data td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.data th {
            background: #f3f4f6;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #4b5563;
        }
        table.data tr:nth-child(even) td {
            background: #f9fafb;
        }
        .empty {
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    @include('reports.partials.organization-header')
    <h1>{{ $department->name }}</h1>
    <p class="meta">
        Department activity report · {{ $periodLabel }} · Generated {{ now()->format('Y-m-d H:i') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <span class="label">Created</span>
                <span class="value">{{ $counts['total'] ?? 0 }}</span>
            </td>
            <td>
                <span class="label">In Progress</span>
                <span class="value">{{ $counts['in_progress'] ?? 0 }}</span>
            </td>
            <td>
                <span class="label">Overdue</span>
                <span class="value">{{ $counts['overdue'] ?? 0 }}</span>
            </td>
            <td>
                <span class="label">Completed</span>
                <span class="value">{{ $counts['completed'] ?? 0 }}</span>
            </td>
        </tr>
    </table>

    @if ($items->isEmpty())
        <p class="empty">No tasks were created in this period.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Task</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    <th>Due</th>
                    <th>Created</th>
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
                        <td>{{ $item->created_at?->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('reports.partials.organization-footer')
</body>
</html>
