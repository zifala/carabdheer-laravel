<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

class Buffer
{
    /** @var list<array<string, mixed>> */
    private array $events = [];

    private bool $flushing = false;

    public function __construct(private readonly Client $client) {}

    /** @param  array<string, mixed>  $event */
    public function push(array $event): void
    {
        if ($this->flushing || ! $this->client->enabled()) {
            return;
        }

        $this->events[] = ['occurred_at' => now()->toIso8601String(), ...$event];

        if (count($this->events) >= 100) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->flushing || $this->events === []) {
            return;
        }

        $this->flushing = true;
        $events = $this->events;
        $this->events = [];

        try {
            $this->client->send($events);
        } finally {
            $this->flushing = false;
        }
    }

    public function isFlushing(): bool
    {
        return $this->flushing;
    }

    /** @return list<array<string, mixed>> */
    public function pending(): array
    {
        return $this->events;
    }
}
