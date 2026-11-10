<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Routing\Route;
use Symfony\Component\Mime\Address;
use Throwable;

class EventShapes
{
    /** @return array<string, mixed> */
    public function request(RequestHandled $event, float $rate): array
    {
        $route = $event->request->route();
        $uri = $route instanceof Route ? '/'.ltrim($route->uri(), '/') : '/'.ltrim($event->request->path(), '/');
        $start = defined('LARAVEL_START') ? LARAVEL_START : $event->request->server('REQUEST_TIME_FLOAT');

        return array_filter([
            'type' => 'request',
            'method' => $event->request->method(),
            'route' => $uri,
            'status' => $event->response->getStatusCode(),
            'duration_ms' => is_numeric($start) ? max(0, (int) round((microtime(true) - (float) $start) * 1000)) : 0,
            'sample_rate' => $rate,
            'user' => $event->request->user() ? ['id' => (string) $event->request->user()->getAuthIdentifier()] : null,
        ], fn (mixed $value) => $value !== null);
    }

    /** @return array<string, mixed> */
    public function query(QueryExecuted $event): array
    {
        return [
            'type' => 'slow_query',
            'sql' => mb_substr($event->sql, 0, 10000),
            'duration_ms' => (int) round($event->time),
            'connection' => $event->connectionName,
            'database' => (string) $event->connection->getDatabaseName(),
        ];
    }

    /** @return array<string, mixed> */
    public function job(JobProcessed|JobFailed|JobExceptionOccurred $event, string $status): array
    {
        $payload = $event->job->payload();
        $pushedAt = $payload['pushedAt'] ?? null;

        return array_filter([
            'type' => 'job',
            'name' => (string) ($payload['displayName'] ?? $event->job->getName()),
            'queue' => $event->job->getQueue(),
            'status' => $status,
            'attempts' => $event->job->attempts(),
            'wait_ms' => is_numeric($pushedAt) ? max(0, (int) round((microtime(true) - (float) $pushedAt) * 1000)) : null,
            'exception' => property_exists($event, 'exception') ? mb_substr($event->exception::class.': '.$event->exception->getMessage(), 0, 2000) : null,
        ], fn (mixed $value) => $value !== null);
    }

    /** @return array<string, mixed> */
    public function task(ScheduledEvent $task, string $status, ?float $runtime, ?Throwable $exception = null): array
    {
        $started = $runtime !== null ? now()->subMilliseconds((int) round($runtime * 1000)) : null;

        return array_filter([
            'type' => 'scheduled_task',
            'name' => mb_substr($task->description ?: (string) $task->command, 0, 255),
            'status' => $status,
            'duration_ms' => $runtime !== null ? (int) round($runtime * 1000) : null,
            'late_ms' => $started !== null ? max(0, $started->getTimestampMs() - $started->copy()->startOfMinute()->getTimestampMs()) : null,
            'exception' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : null,
        ], fn (mixed $value) => $value !== null);
    }

    /** @return list<array<string, mixed>> */
    public function mail(MessageSent $event): array
    {
        $message = $event->message;
        $id = $event->sent->getMessageId();

        return array_values(array_map(fn (Address $address) => array_filter([
            'type' => 'mail',
            'message_id' => $id,
            'subject' => mb_substr((string) $message->getSubject(), 0, 255),
            'to' => $address->getAddress(),
            'status' => 'sent',
            'mailable' => $event->data['__laravel_mailable'] ?? null,
        ], fn (mixed $value) => $value !== null), $message->getTo()));
    }

    /** @return array<string, mixed> */
    public function log(MessageLogged $event): array
    {
        $context = array_map(fn (mixed $value) => is_scalar($value) || $value === null ? $value : get_debug_type($value), array_slice($event->context, 0, 20, true));

        return [
            'type' => 'log',
            'level' => $event->level,
            'source' => app()->runningInConsole() ? 'queue' : 'laravel',
            'message' => mb_substr($event->message, 0, 10000),
            'context' => $context,
        ];
    }

    /** @return array<string, mixed> */
    public function backup(object $event, string $status): array
    {
        $destination = property_exists($event, 'backupDestination') ? $event->backupDestination : null;

        return array_filter([
            'type' => 'backup',
            'status' => $status,
            'name' => is_object($destination) && method_exists($destination, 'backupName') ? $destination->backupName() : null,
            'destination' => is_object($destination) && method_exists($destination, 'diskName') ? $destination->diskName() : null,
        ], fn (mixed $value) => $value !== null);
    }
}
