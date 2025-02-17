<?php

return [
    'enabled' => env('LARAVEL_LOG_MONITOR_ENABLED', true),
    'environments' => env('LARAVEL_LOG_MONITOR_ENVIRONMENTS', 'production'),
    'channels' => [
        'mattermost' => [
            'enabled' => env('LARAVEL_LOG_MONITOR_MATTERMOST_ENABLED', true),
            'url' => env('LARAVEL_LOG_MONITOR_MATTERMOST_URL', ''),
            'token' => env('LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN', ''),
            'notification_levels' => [
                'error' => [
                    'enabled' => env('LARAVEL_LOG_MONITOR_MATTERMOST_ERROR_ENABLED', true),
                    'channel_id' => env('LARAVEL_LOG_MONITOR_MATTERMOST_ERROR_CHANNEL', ''),
                ],
                'info' => [
                    'enabled' => env('LARAVEL_LOG_MONITOR_MATTERMOST_INFO_ENABLED', true),
                    'channel_id' => env('LARAVEL_LOG_MONITOR_MATTERMOST_INFO_CHANNEL', ''),
                ]
            ],
        ],
        'email' => [
            'enabled' => env('LARAVEL_LOG_MONITOR_EMAIL_ENABLED', true),
            'recipients' => env('LARAVEL_LOG_MONITOR_EMAIL_RECIPIENTS', ''),
            'notification_levels' => [
                'error' => env('LARAVEL_LOG_MONITOR_EMAIL_ERROR_ENABLED', true),
                'info' => env('LARAVEL_LOG_MONITOR_EMAIL_INFO_ENABLED', true),
            ],
            'send_as_backup' => env('LARAVEL_LOG_MONITOR_EMAIL_SEND_AS_BACKUP', true)
        ]
    ],
];