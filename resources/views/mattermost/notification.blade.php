@php
    $escapeCell = fn ($value) => str_replace('|', '\|', $value);
    $isMultiline = str_contains(trim($message), "\n");
    $contextJson = $context ? json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
    // A code fence must be longer than any backtick run inside the block
    $fence = function ($content) {
        preg_match_all('/`+/', $content, $matches);
        return str_repeat('`', max(3, ...array_map(fn ($run) => strlen($run) + 1, $matches[0] ?: [''])));
    };
@endphp
| {{ strtoupper($level) }} | {{ $escapeCell(config('app.name')) . " - " . now()->format('d.m.Y H:i:s') }} |
| :--- | :--- |
@if (!$isMultiline)
| Message | {{ $escapeCell(trim($message)) }} |
@endif
@if ($isMultiline)

**Message**
{{ $fence($message) }}
{!! trim($message) !!}
{{ $fence($message) }}
@endif
@if ($contextJson)

**Context**
{{ $fence($contextJson) }}json
{!! $contextJson !!}
{{ $fence($contextJson) }}
@endif
