<?php

namespace GoncaloSoares\FilamentMonitor;

use Filament\Support\Facades\FilamentAsset;
use GoncaloSoares\FilamentMonitor\Commands\MonitorHeartbeatCommand;
use GoncaloSoares\FilamentMonitor\Commands\MonitorPruneCommand;
use GoncaloSoares\FilamentMonitor\Commands\MonitorSilenceCommand;
use GoncaloSoares\FilamentMonitor\Commands\MonitorTestAlertCommand;
use GoncaloSoares\FilamentMonitor\Commands\MonitorWorkCommand;
use Illuminate\Support\ServiceProvider;

class MonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monitor.php', 'monitor');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'monitor');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'monitor');

        $this->publishes([__DIR__.'/../config/monitor.php' => config_path('monitor.php')], 'monitor-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MonitorWorkCommand::class,
                MonitorHeartbeatCommand::class,
                MonitorTestAlertCommand::class,
                MonitorPruneCommand::class,
                MonitorSilenceCommand::class,
            ]);
        }

        FilamentAsset::register([
            MonitorCss::make('monitor', __DIR__.'/../resources/css/monitor.css'),
        ], package: 'goncalosoaresdev/filament-monitor');
    }
}
