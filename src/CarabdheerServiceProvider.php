<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\ServiceProvider;
use Throwable;
use Zifala\Carabdheer\Console\InstallCommand;

class CarabdheerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/carabdheer.php', 'carabdheer');

        $this->app->singleton(Client::class, fn (Application $app) => new Client(
            (string) $app['config']->get('carabdheer.key'),
            (string) $app['config']->get('carabdheer.url'),
            (int) $app['config']->get('carabdheer.timeout'),
        ));
        $this->app->singleton(ExceptionReporter::class);
        $this->app->singleton(Buffer::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/carabdheer.php' => config_path('carabdheer.php')], 'carabdheer-config');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        $this->app->make('events')->subscribe($this->app->make(Listeners::class));
        $this->app->terminating(fn () => $this->app->make(Buffer::class)->flush());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->call(function (): void {
                $stats = $this->app->make(DatabaseStats::class)->read();

                if ($stats !== null) {
                    $this->app->make(Buffer::class)->push($stats);
                    $this->app->make(Buffer::class)->flush();
                }
            })->everyFiveMinutes()->name('carabdheer:database-stats');
        });

        $handler = $this->app->make(ExceptionHandler::class);

        if ($handler instanceof Handler) {
            $handler->reportable(function (Throwable $exception): void {
                $this->app->make(ExceptionReporter::class)->report($exception);
            });
        }
    }
}
