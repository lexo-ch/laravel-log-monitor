| {{ strtoupper($level) }} | {{ str_replace('|', '\|', config('app.name')) . " - " . now()->format('d.m.Y H:i:s') }} |
| :--- | :--- |
| Message | {{ str_replace('|', '\|', $message) }} |
@if ($context)
| Context | `@json($context)` |
@endif
