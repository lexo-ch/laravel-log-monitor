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

        // Load email views
        $this->loadViewsFrom(resource_path('laravel-log-monitor/email-views'), 'laravel-log-monitor-email-views');
        $this->loadViewsFrom(__DIR__.'/../../resources/views/email', 'laravel-log-monitor-email-views');
        
        // Load Mattermost views
        $this->loadViewsFrom(resource_path('laravel-log-monitor/mattermost-views'), 'laravel-log-monitor-mattermost-views');
        $this->loadViewsFrom(__DIR__.'/../../resources/views/mattermost', 'laravel-log-monitor-mattermost-views');

        $this->publishes([
            __DIR__.'/../../config/laravel-log-monitor.php' => config_path('laravel-log-monitor.php')
        ], 'laravel-log-monitor-config');

        $this->publishes([
            __DIR__.'/../../resources/views/email' => resource_path('laravel-log-monitor/email-views')
        ], 'laravel-log-monitor-views-email');
        
        $this->publishes([
            __DIR__.'/../../resources/views/mattermost' => resource_path('laravel-log-monitor/mattermost-views')
        ], 'laravel-log-monitor-views-mattermost');
    }
}
