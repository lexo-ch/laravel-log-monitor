<?php

namespace LEXO\LaravelLogMonitor\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use LEXO\LaravelLogMonitor\Services\LaravelLogMonitorService;

class LaravelLogMonitorServiceProvider extends ServiceProvider
{
    public const PACKAGE_NAME = 'LEXO Laravel Log Monitor';
    public const PACKAGE_GITHUB_URL = 'https://github.com/lexo-ch/laravel-log-monitor';

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(LaravelLogMonitorService::class, function ($app) {
            return new LaravelLogMonitorService();
        });

        $this->mergeConfigFrom(
            __DIR__.'/../../config/laravel-log-monitor.php', 'laravel-log-monitor'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['events']->listen(
            MessageLogged::class,
            LaravelLogMonitorService::class
        );

        $this->loadViewsFrom(resource_path('laravel-log-monitor/views'), 'laravel-log-monitor-email-views');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'laravel-log-monitor-email-views');

        $this->publishes([
            __DIR__.'/../../config/laravel-log-monitor.php' => config_path('laravel-log-monitor.php')
        ], 'laravel-log-monitor-config');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('laravel-log-monitor/views')
        ], 'laravel-log-monitor-views');
    }
}
