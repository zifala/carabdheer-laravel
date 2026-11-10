<?php

declare(strict_types=1);

use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Zifala\Carabdheer\Buffer;
use Zifala\Carabdheer\DatabaseStats;

function sentEvents(): array
{
    $events = [];
    Http::recorded(function (Request $request) use (&$events) {
        $events = [...$events, ...$request['events']];
    });

    return $events;
}

beforeEach(fn () => Http::fake(['carabdheer.test/*' => Http::response(['accepted' => 1], 202)]));

test('a 5xx request is always sent with its route and duration', function () {
    config(['carabdheer.requests.sample_rate' => 0]);
    Route::get('/boom/{id}', fn () => response('no', 500));

    $this->get('/boom/7');
    app(Buffer::class)->flush();

    $event = collect(sentEvents())->firstWhere('type', 'request');
    expect($event['route'])->toBe('/boom/{id}')->and($event['status'])->toBe(500)->and($event['sample_rate'])->toEqual(1.0);
});

test('requests are sampled at the configured rate', function () {
    config(['carabdheer.requests.sample_rate' => 0]);
    Route::get('/ok', fn () => 'ok');

    $this->get('/ok');
    app(Buffer::class)->flush();

    expect(collect(sentEvents())->where('type', 'request'))->toHaveCount(0);
});

test('a slow query over the threshold is sent', function () {
    config(['carabdheer.slow_query_ms' => 0, 'database.default' => 'testing', 'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:']]);

    DB::select('select 1');
    app(Buffer::class)->flush();

    expect(collect(sentEvents())->firstWhere('type', 'slow_query')['sql'])->toBe('select 1');
});

test('warning logs are sent, info logs are not', function () {
    Log::info('quiet');
    Log::warning('disk almost full', ['disk' => '/']);
    app(Buffer::class)->flush();

    $logs = collect(sentEvents())->where('type', 'log');
    expect($logs)->toHaveCount(1)->and($logs->first()['message'])->toBe('disk almost full')->and($logs->first()['context'])->toBe(['disk' => '/']);
});

test('a sent mail is reported once per recipient', function () {
    config(['mail.default' => 'array']);

    Mail::raw('hello', fn ($m) => $m->to(['a@example.com', 'b@example.com'])->subject('welcome'));
    app(Buffer::class)->flush();

    $mails = collect(sentEvents())->where('type', 'mail');
    expect($mails)->toHaveCount(2)->and($mails->first()['subject'])->toBe('welcome')->and($mails->first()['status'])->toBe('sent');
});

test('a scheduled task that finishes is reported', function () {
    $event = app(Schedule::class)->call(fn () => null)->description('prune things');
    event(new ScheduledTaskFinished($event, 0.25));
    app(Buffer::class)->flush();

    $task = collect(sentEvents())->firstWhere('type', 'scheduled_task');
    expect($task['name'])->toBe('prune things')->and($task['status'])->toBe('finished')->and($task['duration_ms'])->toBe(250);
});

test('events are buffered and sent in one batch', function () {
    Log::warning('one');
    Log::warning('two');
    expect(app(Buffer::class)->pending())->toHaveCount(2);
    Http::assertNothingSent();

    app(Buffer::class)->flush();

    Http::assertSentCount(1);
    expect(app(Buffer::class)->pending())->toBe([]);
});

test('the buffer sends on its own at 100 events', function () {
    foreach (range(1, 100) as $i) {
        app(Buffer::class)->push(['type' => 'log', 'level' => 'warning', 'message' => "line {$i}"]);
    }

    Http::assertSentCount(1);
});

test('a processed job is reported and flushed at once', function () {
    config(['queue.default' => 'sync']);

    dispatch(fn () => null);

    $job = collect(sentEvents())->firstWhere('type', 'job');
    expect($job['status'])->toBe('processed')->and($job['attempts'])->toBe(1);
});

test('a failed job is reported as failed with its exception', function () {
    config(['queue.default' => 'sync']);

    try {
        dispatch(fn () => throw new RuntimeException('gateway timeout'));
    } catch (RuntimeException) {
    }

    $job = collect(sentEvents())->firstWhere('type', 'job');
    expect($job['status'])->toBe('failed')->and($job['exception'])->toContain('gateway timeout');
});

test('database stats are read from the default connection', function () {
    $file = tempnam(sys_get_temp_dir(), 'crb').'.sqlite';
    touch($file);
    config(['database.default' => 'stats', 'database.connections.stats' => ['driver' => 'sqlite', 'database' => $file]]);
    DB::statement('create table t (id integer)');

    $stats = app(DatabaseStats::class)->read();

    expect($stats)->toMatchArray(['type' => 'database_stats', 'engine' => 'sqlite', 'name' => basename($file), 'connections' => 1])
        ->and($stats['size_bytes'])->toBeGreaterThan(0);
    unlink($file);
});

test('the scheduler sends database stats every five minutes', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'carabdheer:database-stats');

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});

test('a reported exception is sent once, without the log line Laravel writes for it', function () {
    app(ExceptionHandler::class)->report(new RuntimeException('boom'));
    Log::error('payment failed', ['exception' => new RuntimeException('other')]);
    Log::error('manual line');
    app(Buffer::class)->flush();

    $events = collect(sentEvents());
    expect($events->where('type', 'exception'))->toHaveCount(1)
        ->and($events->where('type', 'log')->pluck('message')->all())->toBe(['payment failed', 'manual line']);
});
