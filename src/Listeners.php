<?php

declare(strict_types=1);

namespace Zifala\Carabdheer;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Psr\Log\LogLevel;

class Listeners
{
    private const LEVELS = [LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE, LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY];

    public function __construct(private readonly Buffer $buffer, private readonly EventShapes $shapes, private readonly ExceptionReporter $reporter) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(RequestHandled::class, fn (RequestHandled $e) => $this->request($e));
        $events->listen(QueryExecuted::class, fn (QueryExecuted $e) => $this->query($e));
        $events->listen(JobProcessed::class, fn (JobProcessed $e) => $this->job($e, 'processed'));
        $events->listen(JobFailed::class, fn (JobFailed $e) => $this->job($e, 'failed'));
        $events->listen(JobExceptionOccurred::class, fn (JobExceptionOccurred $e) => $e->job->isReleased() ? $this->job($e, 'retrying') : null);
        $events->listen(ScheduledTaskFinished::class, fn (ScheduledTaskFinished $e) => $this->buffer->push($this->shapes->task($e->task, 'finished', $e->runtime)));
        $events->listen(ScheduledTaskFailed::class, fn (ScheduledTaskFailed $e) => $this->buffer->push($this->shapes->task($e->task, 'failed', null, $e->exception)));
        $events->listen(MessageSent::class, fn (MessageSent $e) => array_map($this->buffer->push(...), $this->shapes->mail($e)));
        $events->listen(MessageLogged::class, fn (MessageLogged $e) => $this->log($e));

        foreach (['Spatie\Backup\Events\BackupWasSuccessful' => 'success', 'Spatie\Backup\Events\BackupHasFailed' => 'failed'] as $class => $status) {
            $events->listen($class, fn (object $e) => $this->buffer->push($this->shapes->backup($e, $status)));
        }
    }

    private function request(RequestHandled $event): void
    {
        $status = $event->response->getStatusCode();
        $rate = (float) config('carabdheer.requests.sample_rate');

        if ($status >= 500 || random_int(1, 10000) <= $rate * 10000) {
            $this->buffer->push($this->shapes->request($event, $status >= 500 ? 1.0 : $rate));
        }
    }

    private function query(QueryExecuted $event): void
    {
        if ($event->time >= (int) config('carabdheer.slow_query_ms')) {
            $this->buffer->push($this->shapes->query($event));
        }
    }

    private function job(JobProcessed|JobFailed|JobExceptionOccurred $event, string $status): void
    {
        $this->buffer->push($this->shapes->job($event, $status));

        if ($status !== 'retrying') {
            $this->buffer->flush();
        }
    }

    private function log(MessageLogged $event): void
    {
        $minimum = array_search(config('carabdheer.log_level'), self::LEVELS, true);

        if ($this->buffer->isFlushing() || array_search($event->level, self::LEVELS, true) < $minimum || $this->reporter->justReported($event->context['exception'] ?? null)) {
            return;
        }

        $this->buffer->push($this->shapes->log($event));
    }
}
