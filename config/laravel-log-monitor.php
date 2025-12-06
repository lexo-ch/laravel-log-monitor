<?php

return [
    'enabled' => env('LARAVEL_LOG_MONITOR_ENABLED', false),
    'environments' => env('LARAVEL_LOG_MONITOR_ENVIRONMENTS', 'production'),
    'channels' => [
        'mattermost' => [
            'enabled' => env('LARAVEL_LOG_MONITOR_MATTERMOST_ENABLED', true),
            'url' => env('LARAVEL_LOG_MONITOR_MATTERMOST_URL', ''),
            'token' => env('LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN', ''),
            'channel_id' => env('LARAVEL_LOG_MONITOR_MATTERMOST_CHANNEL', ''),
            'retry_times' => env('LARAVEL_LOG_MONITOR_MATTERMOST_RETRY_TIMES', 3),
            'retry_delay' => env('LARAVEL_LOG_MONITOR_MATTERMOST_RETRY_DELAY', 100),
            'timeout' => env('LARAVEL_LOG_MONITOR_MATTERMOST_TIMEOUT', 10),
        ],
        'email' => [
            'enabled' => env('LARAVEL_LOG_MONITOR_EMAIL_ENABLED', true),
            'recipients' => env('LARAVEL_LOG_MONITOR_EMAIL_RECIPIENTS', ''),
            'send_as_backup' => env('LARAVEL_LOG_MONITOR_EMAIL_SEND_AS_BACKUP', true)
        ]
    ],
];