<?php

use LEXO\LaravelLogMonitor\Services\LaravelLogMonitorService;

beforeEach(function () {
    config([
        'laravel-log-monitor.enabled' => true,
        'laravel-log-monitor.environments' => 'testing',
    ]);
});

it('throws exception when mattermost is enabled but url is missing', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => '',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Mattermost URL is required when Mattermost is enabled');

it('throws exception when mattermost is enabled but token is missing', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => '',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Mattermost token is required when Mattermost is enabled');

it('throws exception when mattermost is enabled but channel_id is missing', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => '',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Mattermost channel_id is required when Mattermost is enabled');

it('throws exception when additional channel has no channel_ids', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => []
        ],
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, "Mattermost additional channel 'payments' has no channel_ids configured");

it('throws exception when additional channel has empty channel_id', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => ['']
        ],
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, "Mattermost additional channel 'payments' has empty channel_id");

it('accepts valid additional channels configuration with single channel', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'payments' => 'payment-channel-id'
        ],
        'laravel-log-monitor.channels.email.enabled' => false,
    ]);

    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('accepts valid additional channels configuration with multiple channels', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => 'https://mattermost.example.com',
        'laravel-log-monitor.channels.mattermost.token' => 'test-token',
        'laravel-log-monitor.channels.mattermost.channel_id' => 'test-channel',
        'laravel-log-monitor.channels.mattermost.additional_channels' => [
            'critical' => ['channel-1', 'channel-2']
        ],
        'laravel-log-monitor.channels.email.enabled' => false,
    ]);

    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('throws exception when email is enabled but recipients is missing', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => '',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Email recipients are required when email notifications are enabled');

it('throws exception when email recipient is invalid', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'invalid-email',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Invalid email address: invalid-email');

it('throws exception when one of multiple email recipients is invalid', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'valid@example.com, invalid-email',
    ]);

    app(LaravelLogMonitorService::class);
})->throws(\InvalidArgumentException::class, 'Invalid email address: invalid-email');

it('accepts valid email configuration', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'test@example.com',
    ]);

    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('accepts multiple valid email recipients', function () {
    config([
        'laravel-log-monitor.channels.mattermost.enabled' => false,
        'laravel-log-monitor.channels.email.enabled' => true,
        'laravel-log-monitor.channels.email.recipients' => 'test1@example.com, test2@example.com',
    ]);

    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('does not validate when service is disabled', function () {
    config([
        'laravel-log-monitor.enabled' => false,
        'laravel-log-monitor.channels.mattermost.enabled' => true,
        'laravel-log-monitor.channels.mattermost.url' => '',
    ]);

    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});
