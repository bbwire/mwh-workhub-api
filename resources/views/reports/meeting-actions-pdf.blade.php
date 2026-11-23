<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meeting->title }} — Actions</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f2744; }
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
    <h1>{{ $meeting->title }}</h1>
    <p class="meta">
        Meeting actions · Held {{ $meeting->held_at?->format('Y-m-d H:i') }} · Generated {{ now()->format('Y-m-d H:i') }}
    </p>
    @if (filled($meeting->minutes))
        <h2 style="font-size: 13px; margin: 0 0 6px; color: #0f2744;">Minutes</h2>
        <div style="margin-bottom: 16px; line-height: 1.45;">
            {!! $meeting->minutes !!}
        </div>
    @endif
    @if ($items->isEmpty())
        <p class="empty">No action items for this meeting.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Action</th>
                    <th>Type</th>
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
                        <td>{{ $item->workType?->name ?? '—' }}</td>
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
