<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 20px;
        }
        .container {
            margin: 0 auto;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .level {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
            color: white;
            background-color: #dc3545;  /* Default red for error */
        }
        .level.info {
            background-color: #2083fc;
        }
        .datetime {
            color: #666;
            font-size: 0.9em;
        }
        .message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .context {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
            text-align: center;
        }
        .footer a {
            color: #0366d6;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .separator {
            margin-left: 5px;
            margin-right: 5px;
            font-size: 16px;
            color: silver;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="level {{ strtolower($emailData['level']) }}">{{ strtoupper($emailData['level']) }}</span>
            <span class="separator">—</span>
            <span>APP NAME: {{ config('app.name') }}</span>
            <span class="separator">—</span>
            <span>ENV: {{ app()->environment() }}</span>
        </div>

        <div class="message">
            {{ $emailData['message'] }}
        </div>

        @if(!empty($emailData['context']))
            <h3>Context Details</h3>
            <div class="context">@json($emailData['context'])</div>
        @endif

        <div class="footer">
            Powered by <a href="{{ $packageGithubUrl }}" target="_blank">{{ $packageName }}</a>
        </div>
    </div>
</body>
</html>