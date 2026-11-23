<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My workload — {{ $user->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f2744; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .summary td { border: 1px solid #e5e7eb; padding: 8px 10px; width: 33.33%; }
        .summary .label { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; margin-bottom: 2px; }
        .summary .value { font-size: 16px; font-weight: bold; color: #0f2744; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        table.data th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #4b5563; }
        table.data tr:nth-child(even) td { background: #f9fafb; }
        .empty { color: #6b7280; font-style: italic; }
        .overdue { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    @include('reports.partials.organization-header')
    <h1>My workload</h1>
    <p class="meta">
        Open tasks for {{ $user->name }} · Generated {{ now()->format('Y-m-d H:i') }}
    </p>

    <table class="summary">
        <tr>
            <td>
                <span class="label">Open</span>
                <span class="value">{{ $counts['open'] }}</span>
            </td>
            <td>
                <span class="label">Overdue</span>
                <span class="value">{{ $counts['overdue'] }}</span>
            </td>
            <td>
                <span class="label">Due today</span>
                <span class="value">{{ $counts['due_today'] }}</span>
            </td>
        </tr>
    </table>

    @if ($items->isEmpty())
        <p class="empty">No open tasks right now.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Task</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Your role</th>
                    <th>Assignee</th>
                    <th>Due</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    @php
                        $roles = [];
                        if ($item->assignee_id === $user->id) {
                            $roles[] = 'Assignee';
                        }
                        if ($item->requester_id === $user->id) {
                            $roles[] = 'Requester';
                        }
                        $isOverdue = $item->due_at !== null && $item->due_at->isPast();
                    @endphp
                    <tr>
                        <td>{{ $item->reference_number }}</td>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->status?->label ?? '—' }}</td>
                        <td>{{ ucfirst((string) ($item->priority?->value ?? $item->priority ?? '—')) }}</td>
                        <td>{{ implode(' · ', $roles) ?: '—' }}</td>
                        <td>{{ $item->assignee?->name ?? 'Unassigned' }}</td>
                        <td class="{{ $isOverdue ? 'overdue' : '' }}">
                            {{ $item->due_at?->format('Y-m-d') ?? '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('reports.partials.organization-footer')
</body>
</html>
