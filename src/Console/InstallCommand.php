<?php

declare(strict_types=1);

namespace Zifala\Carabdheer\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Zifala\Carabdheer\Client;
use Zifala\Carabdheer\ExceptionReporter;

class InstallCommand extends Command
{
    protected $signature = 'carabdheer:install';

    protected $description = 'Publish the Carabdheer config, add the key line and send a test event';

    public function handle(Filesystem $files, Client $client, ExceptionReporter $reporter): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'carabdheer-config']);
        $this->line('config written to config/carabdheer.php');

        $env = $this->laravel->environmentFilePath();

        if ($files->exists($env) && ! str_contains($files->get($env), 'CARABDHEER_KEY=')) {
            $files->append($env, PHP_EOL.'CARABDHEER_KEY='.PHP_EOL);
            $this->line('added CARABDHEER_KEY= to .env. Paste the key from "let carabdheer in".');
        }

        if (! $client->enabled()) {
            $this->warn('CARABDHEER_KEY is empty. Set it, then run this command again to send a test event.');

            return self::SUCCESS;
        }

        $status = $client->send([$reporter->event(new RuntimeException('Carabdheer test event from carabdheer:install'))]);

        if ($status === 202) {
            $this->info('Carabdheer heard the test event. Open the errors page to see it.');

            return self::SUCCESS;
        }

        $this->error($status === null ? 'Could not reach Carabdheer. Check CARABDHEER_URL and the network.' : "Carabdheer answered {$status}. Check the key.");

        return self::FAILURE;
    }
}
