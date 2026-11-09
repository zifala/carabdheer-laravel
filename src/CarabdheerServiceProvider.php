<?php

declare(strict_types=1);

namespace Carabdheer\Carabdheer;

use Carabdheer\Carabdheer\Console\Commands\CarabdheerCommand;
use Illuminate\Support\ServiceProvider;

class CarabdheerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/carabdheer-laravel.php', 'carabdheer-laravel');

        $this->app->singleton(Carabdheer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/carabdheer-laravel.php' => config_path('carabdheer-laravel.php'),
        ], ['carabdheer-laravel', 'carabdheer-laravel-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['carabdheer-laravel', 'carabdheer-laravel-migrations']);

        $this->commands([
            CarabdheerCommand::class,
        ]);
    }
}
