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
# Environments where monitoring is active (comma-separated)
LARAVEL_LOG_MONITOR_ENVIRONMENTS=production,local

# Mattermost integration settings
LARAVEL_LOG_MONITOR_MATTERMOST_URL=https://your-mattermost-url.tld
LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN=your-mattermost-token
LARAVEL_LOG_MONITOR_MATTERMOST_CHANNEL=mattermost-channel-id

# Email notification recipients (comma-separated)
LARAVEL_LOG_MONITOR_EMAIL_RECIPIENTS=email1@website.tld,email2@website.tld
```

By default:

Only the production environment is monitored
Email notifications serve as a backup if Mattermost notifications fail

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