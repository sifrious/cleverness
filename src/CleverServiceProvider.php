<?php

declare(strict_types=1);

namespace Clever\Clever;

use Clever\Clever\Console\Commands\HotspotsCommand;
use Clever\Clever\Console\Commands\LonelyFilesCommand;
use Clever\Clever\Console\Commands\OwnedDiffCommand;
use Clever\Clever\Console\Commands\ScanCommand;
use Clever\Clever\Console\Commands\WeldsCommand;
use Illuminate\Support\ServiceProvider;

class CleverServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/clever.php', 'clever');

        $this->app->singleton(Clever::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'clever');

        $enabled = $this->app->make(Clever::class)->enabled();

        if ($enabled) {
            $this->loadRoutesFrom(__DIR__.'/../routes/clever.php');
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/clever.php' => config_path('clever.php'),
        ], ['clever', 'clever-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/clever'),
        ], ['clever', 'clever-views']);

        if ($enabled) {
            $this->commands([
                ScanCommand::class,
                OwnedDiffCommand::class,
                WeldsCommand::class,
                LonelyFilesCommand::class,
                HotspotsCommand::class,
            ]);
        }
    }
}
