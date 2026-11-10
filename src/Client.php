<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Support\Facades\Http;
use Throwable;

class Client
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly ?string $key,
        private readonly string $url,
        private readonly int $timeout,
    ) {}

    public function enabled(): bool
    {
        return is_string($this->key) && str_starts_with($this->key, 'crb_');
    }

    /** @param  list<array<string, mixed>>  $events */
    public function send(array $events): ?int
    {
        if (! $this->enabled() || $events === []) {
            return null;
        }

        try {
            return Http::withToken((string) $this->key)
                ->withUserAgent('carabdheer-laravel/'.self::VERSION)
                ->acceptJson()
                ->timeout($this->timeout)
                ->connectTimeout($this->timeout)
                ->post($this->url, ['events' => array_slice($events, 0, 100)])
                ->status();
        } catch (Throwable) {
            return null;
        }
    }
}
