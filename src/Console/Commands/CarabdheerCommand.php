<?php

declare(strict_types=1);

namespace Carabdheer\Carabdheer\Console\Commands;

use Illuminate\Console\Command;

class CarabdheerCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'carabdheer-laravel:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package carabdheer-laravel.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('Carabdheer placeholder command executed.');

        return self::SUCCESS;
    }
}
