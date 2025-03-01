| {{ strtoupper($level) }} | {{ config('app.name') . " - " . now()->format('d.m.Y H:i:s') }}
| :--- | :---
| Message | {{ $message }}
@if ($context)
| Context | `@json($context)`
@endif