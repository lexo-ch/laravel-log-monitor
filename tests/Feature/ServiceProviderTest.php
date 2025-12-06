<?php

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use LEXO\LaravelLogMonitor\Services\LaravelLogMonitorService;

it('loads the service provider', function () {
    expect(app()->bound(LaravelLogMonitorService::class))->toBeTrue();
});

it('can resolve the log monitor service', function () {
    $service = app(LaravelLogMonitorService::class);

    expect($service)->toBeInstanceOf(LaravelLogMonitorService::class);
});

it('registers service as singleton', function () {
    $service1 = app(LaravelLogMonitorService::class);
    $service2 = app(LaravelLogMonitorService::class);

    expect($service1)->toBe($service2);
});

it('merges config from package config file', function () {
    expect(config('laravel-log-monitor'))->toBeArray()
        ->and(config('laravel-log-monitor'))->toHaveKey('enabled')
        ->and(config('laravel-log-monitor'))->toHaveKey('environments')
        ->and(config('laravel-log-monitor'))->toHaveKey('channels');
});

it('has mattermost channel configuration', function () {
    expect(config('laravel-log-monitor.channels.mattermost'))->toBeArray()
        ->and(config('laravel-log-monitor.channels.mattermost'))->toHaveKey('enabled')
        ->and(config('laravel-log-monitor.channels.mattermost'))->toHaveKey('url')
        ->and(config('laravel-log-monitor.channels.mattermost'))->toHaveKey('token')
        ->and(config('laravel-log-monitor.channels.mattermost'))->toHaveKey('channel_id');
});

it('has email channel configuration', function () {
    expect(config('laravel-log-monitor.channels.email'))->toBeArray()
        ->and(config('laravel-log-monitor.channels.email'))->toHaveKey('enabled')
        ->and(config('laravel-log-monitor.channels.email'))->toHaveKey('recipients')
        ->and(config('laravel-log-monitor.channels.email'))->toHaveKey('send_as_backup');
});

it('listens to MessageLogged events', function () {
    $listeners = Event::getRawListeners()[MessageLogged::class] ?? [];

    $hasListener = false;
    foreach ($listeners as $listener) {
        if (is_string($listener) && $listener === LaravelLogMonitorService::class) {
            $hasListener = true;
            break;
        }
    }

    expect($hasListener)->toBeTrue();
});

it('loads email views from package directory', function () {
    $viewPaths = app('view')->getFinder()->getPaths();
    $hints = app('view')->getFinder()->getHints();

    expect($hints)->toHaveKey('laravel-log-monitor-email-views');
});

it('loads mattermost views from package directory', function () {
    $hints = app('view')->getFinder()->getHints();

    expect($hints)->toHaveKey('laravel-log-monitor-mattermost-views');
});

it('has correct package name constant', function () {
    expect(\LEXO\LaravelLogMonitor\Providers\LaravelLogMonitorServiceProvider::PACKAGE_NAME)
        ->toBe('LEXO Laravel Log Monitor');
});

it('has correct package github url constant', function () {
    expect(\LEXO\LaravelLogMonitor\Providers\LaravelLogMonitorServiceProvider::PACKAGE_GITHUB_URL)
        ->toBe('https://github.com/lexo-ch/laravel-log-monitor');
});
