# Laravel Log Monitor

Get notifications upon writing the Log messages.

## Usage

Install it with Composer in your Laravel app using

```bash
composer require lexo/laravel-log-monitor
```

## ENV variables

In order to use this package you would need to set some ENV varuiables

```
LARAVEL_LOG_MONITOR_ENVIRONMENTS=production,local
LARAVEL_LOG_MONITOR_MATTERMOST_URL=https://your-mattermost-url.tld
LARAVEL_LOG_MONITOR_MATTERMOST_TOKEN=your-mattermost-token
LARAVEL_LOG_MONITOR_MATTERMOST_ERROR_CHANNEL=error-channel-id
LARAVEL_LOG_MONITOR_MATTERMOST_INFO_CHANNEL=info-channel-id
LARAVEL_LOG_MONITOR_EMAIL_RECIPIENTS=email1@website.tld,email2@website.tld
```

By default, only `production` environment is set. Also, E-Mail notifications are being used only as a backup if Mattermost notifications fail. If you want to enable simultaneously Mattermost and E-Mail notifications set env variable `LARAVEL_LOG_MONITOR_EMAIL_SEND_AS_BACKUP` to `false`.

The full list of available env variables can be found in config file.

### Mattermost priority post settings

Mattermost priority has `important` and `urgent` values. This can be passes as a part of the Log message context.

Example:

```php
Log::error('test error', [
    'priority' => 'important',
    'something' => 'else'
]);
```

## Publishing files (optional)

You can publish config file using this command

```bash
php artisan vendor:publish --tag=laravel-log-monitor-config
```

Also you can publish views files using this command

```bash
php artisan vendor:publish --tag=laravel-log-monitor-views
```