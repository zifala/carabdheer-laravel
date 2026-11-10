<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Http\Request;
use Throwable;

class ExceptionReporter
{
    private const HIDDEN_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-csrf-token', 'x-xsrf-token', 'php-auth-pw'];

    private bool $reporting = false;

    private ?Throwable $last = null;

    public function __construct(private readonly Client $client) {}

    public function report(Throwable $exception): void
    {
        if ($this->reporting || ! $this->client->enabled()) {
            return;
        }

        $this->reporting = true;
        $this->last = $exception;

        try {
            $this->client->send([$this->event($exception)]);
        } catch (Throwable) {
            return;
        } finally {
            $this->reporting = false;
        }
    }

    public function justReported(mixed $exception): bool
    {
        return $exception !== null && $exception === $this->last;
    }

    /** @return array<string, mixed> */
    public function event(Throwable $exception): array
    {
        return array_filter([
            'type' => 'exception',
            'occurred_at' => now()->toIso8601String(),
            'class' => $exception::class,
            'message' => mb_substr($exception->getMessage(), 0, 10000),
            'file' => $this->relative($exception->getFile()),
            'line' => $exception->getLine(),
            'trace' => $this->trace($exception),
            'request' => $this->request(),
            'user' => $this->user(),
            'environment' => app()->environment(),
            'release' => config('carabdheer.release'),
        ], fn (mixed $value) => $value !== null);
    }

    /** @return list<array<string, mixed>> */
    private function trace(Throwable $exception): array
    {
        return array_map(fn (array $frame) => array_filter([
            'file' => isset($frame['file']) ? $this->relative($frame['file']) : null,
            'line' => $frame['line'] ?? null,
            'function' => $frame['function'],
            'class' => $frame['class'] ?? null,
            'in_app' => isset($frame['file']) && ! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR),
        ], fn (mixed $value) => $value !== null), array_slice($exception->getTrace(), 0, 100));
    }

    /** @return array<string, mixed>|null */
    private function request(): ?array
    {
        if (! app()->bound('request') || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return null;
        }

        /** @var Request $request */
        $request = app('request');
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = in_array(strtolower($name), self::HIDDEN_HEADERS, true) ? '[filtered]' : implode(', ', $values);
        }

        return ['method' => $request->method(), 'url' => $request->fullUrl(), 'headers' => array_slice($headers, 0, 50, true)];
    }

    /** @return array{id: string}|null */
    private function user(): ?array
    {
        try {
            $id = auth()->id();
        } catch (Throwable) {
            return null;
        }

        return $id === null ? null : ['id' => (string) $id];
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }
}
