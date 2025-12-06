# Laravel Log Monitor

Get notifications when log messages are written in your Laravel application.

## Overview

Laravel Log Monitor sends you alerts via Mattermost and/or email when important log events occur in your application. This helps you stay on top of critical issues and respond quickly to potential problems.

## Installation

Install it with Composer in your Laravel app using

```bash
composer require lexo/laravel-log-monitor
```

## ENV variables

Configure the package by adding these variables to your .env file:

```
LARAVEL_LOG_MONITOR_ENABLED=true

# Environments where monitoring is active (comma-separated)
LARAVEL_LOG_MONITOR_ENVIRONMENTS=production,local

# Mattermost integration settings
LARAVEL_LOG_MONITOR_MATTERMOST_URL=https://your-mattermost-url.tld
LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN=your-mattermost-token
LARAVEL_LOG_MONITOR_MATTERMOST_CHANNEL=mattermost-channel-id

# Optional: Mattermost HTTP retry settings (defaults shown)
LARAVEL_LOG_MONITOR_MATTERMOST_RETRY_TIMES=3
LARAVEL_LOG_MONITOR_MATTERMOST_RETRY_DELAY=100
LARAVEL_LOG_MONITOR_MATTERMOST_TIMEOUT=10

# Email notification recipients (comma-separated)
LARAVEL_LOG_MONITOR_EMAIL_RECIPIENTS=email1@website.tld,email2@website.tld
```

By default:

Only the production environment is monitored.
Email notifications serve as a backup if Mattermost notifications fail.
Mattermost HTTP requests will retry 3 times with 100ms delay and 10 second timeout.

To enable simultaneous Mattermost and email notifications, set in .env file:

```
LARAVEL_LOG_MONITOR_EMAIL_SEND_AS_BACKUP=false
```

The complete list of configuration options is available in the config file.

### Mattermost Priority Settings

You can set Mattermost notification priority using the `llm` context key in your log messages:

```php
Log::critical('test error', [
    'llm' => [
        'priority' => 'important',
        'alert' => true
    ],
    'something' => 'else'
]);
```

Notes:

- Logs of `error` type are always monitored by default
- Other log types can be included by adding `'alert' => true` in the `llm` context
- Priority can be set to `important` or `urgent`

### Multiple Mattermost Channels

You can configure multiple Mattermost channels and route specific logs to specific channels:

#### Configuration

Publish and edit the config file:

```bash
php artisan vendor:publish --tag=laravel-log-monitor-config
```

Then in `config/laravel-log-monitor.php`:

```php
'mattermost' => [
    'enabled' => env('LARAVEL_LOG_MONITOR_MATTERMOST_ENABLED', true),
    'url' => env('LARAVEL_LOG_MONITOR_MATTERMOST_URL', ''),
    'token' => env('LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN', ''),
    'channel_id' => env('LARAVEL_LOG_MONITOR_MATTERMOST_CHANNEL', ''), // Default channel

    // Additional named channels for routing
    // Supports both single channel (string) and multiple channels (array)
    'additional_channels' => [
        // Single channel examples
        'payments' => 'channel-id-for-payments',
        'security' => 'channel-id-for-security',
        'web-errors' => 'channel-id-web-monitoring',
        'monitoring' => 'channel-id-general-monitoring',

        // Multiple channels examples - sends to ALL of these
        'critical' => [
            'channel-id-for-oncall',
            'channel-id-for-management',
            'channel-id-for-devops',
        ],

        'high-priority' => [
            'channel-id-team-leads',
            'channel-id-senior-devs',
        ],
    ],
],
```

#### Usage

Route logs to specific channels using the `channel` key in `llm` context:

```php
// Send to default channel
Log::error('General application error');

// Send to single predefined channel
Log::critical('Payment gateway timeout', [
    'llm' => [
        'channel' => 'payments',  // Single channel name
        'priority' => 'urgent',
    ],
    'gateway' => 'stripe',
    'amount' => 150.00,
]);

// Send to multiple predefined channels at once
Log::error('Website errors detected', [
    'llm' => [
        'channel' => ['web-errors', 'monitoring', 'default'],  // Array of channel names
        'priority' => 'important',
    ],
    'errors_count' => count($errors),
]);

// Use the special 'default' keyword to include default channel
Log::emergency('Critical system failure', [
    'llm' => [
        'channel' => ['critical', 'default'],  // Both critical + default channels
        'priority' => 'urgent',
    ],
]);

// If 'critical' has multiple IDs, this expands to:
// - All channels in 'critical' array
// - Plus the default channel_id
```

#### How It Works

**Channel Resolution:**
- `'channel' => 'name'` - Routes to single channel name (can resolve to multiple IDs if configured as array)
- `'channel' => ['name1', 'name2']` - Routes to multiple channel names (each can resolve to multiple IDs)
- `'channel' => 'default'` or in array `['critical', 'default']` - Uses the default `channel_id`
- If `channel` not specified - Uses default `channel_id`

**Configuration:**
- Each channel name can map to single ID (string) or multiple IDs (array)
- When specifying array of channel names, all resolved IDs are combined
- Duplicate channel IDs are automatically removed
- All channels share the same URL and token

**Error Handling:**
- Validation ensures all configured channels have valid channel IDs
- Email backup is triggered only if sending fails to at least one channel
- Each channel send fires individual events (NotificationSending/Sent/Failed)

#### Example Use Cases

**Team-based routing:**
```php
'additional_channels' => [
    'backend' => 'channel-id-backend-team',
    'frontend' => 'channel-id-frontend-team',
    'devops' => 'channel-id-devops-team',
],
```

**Severity-based routing:**
```php
'additional_channels' => [
    'critical' => 'channel-id-oncall',
    'warnings' => 'channel-id-monitoring',
],
```

**Feature-based routing:**
```php
'additional_channels' => [
    'payments' => 'channel-id-payment-alerts',
    'auth' => 'channel-id-security',
    'api' => 'channel-id-api-monitoring',
],
```

**Broadcast to multiple channels:**
```php
'additional_channels' => [
    // Critical issues go to multiple teams
    'critical' => [
        'channel-id-oncall-pager',
        'channel-id-management',
        'channel-id-engineering-leads',
    ],

    // Security issues alert both teams
    'security' => [
        'channel-id-security-team',
        'channel-id-devops-team',
    ],

    // Payment issues notify both finance and tech
    'payments' => [
        'channel-id-finance-team',
        'channel-id-payment-engineers',
    ],
],
```

**Dynamic multi-channel routing (array of channel names):**
```php
// Using the same config as above, you can send to multiple channels dynamically:
Log::channel('error-monitoring')->error(
    "Website: {$website->url} - Found " . count($errors) . " error(s)",
    [
        'errors' => $errors,
        'error-type' => 'log-website-errors',
        'llm' => [
            'priority' => 'urgent',
            'channel' => ['web-errors', 'monitoring', 'default'],  // 3+ channels
        ]
    ]
);

// This sends to:
// - channel-id-web-monitoring (from 'web-errors')
// - channel-id-general-monitoring (from 'monitoring')
// - default channel_id (from 'default' keyword)
```

### Notification Events

The package fires events during the notification lifecycle, allowing you to hook into and customize the behavior:

#### Available Events

**NotificationSending** - Fired before attempting to send a notification
```php
use LEXO\LaravelLogMonitor\Events\NotificationSending;

Event::listen(NotificationSending::class, function (NotificationSending $event) {
    // $event->event - The original MessageLogged event
    // $event->channel - 'mattermost' or 'email'
    // $event->payload - The data being sent

    Log::info("Sending notification via {$event->channel}");
});
```

**NotificationSent** - Fired after successful notification delivery
```php
use LEXO\LaravelLogMonitor\Events\NotificationSent;

Event::listen(NotificationSent::class, function (NotificationSent $event) {
    // $event->event - The original MessageLogged event
    // $event->channel - 'mattermost' or 'email'
    // $event->response - Response data from the channel

    Log::info("Notification sent successfully via {$event->channel}");
});
```

**NotificationFailed** - Fired when notification delivery fails
```php
use LEXO\LaravelLogMonitor\Events\NotificationFailed;

Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
    // $event->event - The original MessageLogged event
    // $event->channel - 'mattermost' or 'email'
    // $event->exception - The exception that occurred

    // Send to alternative monitoring service
    SentryService::captureException($event->exception);
});
```

#### Example: Custom Slack Notification on Failure

```php
// In AppServiceProvider or EventServiceProvider
use LEXO\LaravelLogMonitor\Events\NotificationFailed;

Event::listen(NotificationFailed::class, function (NotificationFailed $event) {
    if ($event->channel === 'mattermost') {
        // Fallback to Slack if Mattermost fails
        Http::post(config('services.slack.webhook'), [
            'text' => "Mattermost notification failed: {$event->exception->getMessage()}"
        ]);
    }
});
```

## Customization (Optional)

### Publishing Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=laravel-log-monitor-config
```

### Publishing View Templates

#### E-Mail

Customize the notification templates by publishing the view files:

```bash
php artisan vendor:publish --tag=laravel-log-monitor-views-email
```

#### Mattermost

Customize the notification templates by publishing the view files:

```bash
php artisan vendor:publish --tag=laravel-log-monitor-views-mattermost
```