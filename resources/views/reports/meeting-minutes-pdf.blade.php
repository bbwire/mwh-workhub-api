<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meeting->title }} — Minutes</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 24px; line-height: 1.5; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #0f2744; }
        h2 { font-size: 13px; margin: 18px 0 8px; color: #0f2744; }
        .meta { color: #6b7280; margin-bottom: 16px; }
        .minutes p { margin: 0 0 8px; }
        .minutes ul, .minutes ol { margin: 0 0 10px 18px; padding: 0; }
        .minutes li { margin-bottom: 4px; }
        .empty { color: #6b7280; font-style: italic; }
    </style>
</head>
<body>
    @include('reports.partials.organization-header')
    <h1>{{ $meeting->title }}</h1>
    <p class="meta">
        Meeting minutes · Held {{ $meeting->held_at?->format('Y-m-d H:i') }}
        @if ($meeting->creator)
            · Recorded by {{ $meeting->creator->name }}
        @endif
        · Generated {{ now()->format('Y-m-d H:i') }}
    </p>

    <h2>Minutes</h2>
    @php
        $body = filled($meeting->minutes) ? $meeting->minutes : $meeting->notes;
    @endphp
    @if (filled($body))
        <div class="minutes">
            {!! $body !!}
        </div>
    @else
        <p class="empty">No written minutes recorded for this meeting.</p>
    @endif

    @include('reports.partials.organization-footer')
</body>
</html>
