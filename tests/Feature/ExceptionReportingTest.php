<?php

declare(strict_types=1);

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Zifala\Carabdheer\Client;
use Zifala\Carabdheer\ExceptionReporter;

test('a reported exception is sent as one exception event with the bearer key', function () {
    Http::fake(['carabdheer.test/*' => Http::response(['accepted' => 1], 202)]);

    app(ExceptionHandler::class)->report(new RuntimeException('boom'));

    Http::assertSent(function (Request $request) {
        $event = $request['events'][0];

        return $request->url() === 'https://carabdheer.test/api/v1/ingest'
            && $request->hasHeader('Authorization', 'Bearer crb_testkey0000000000000000000000000000000')
            && $event['type'] === 'exception'
            && $event['class'] === RuntimeException::class
            && $event['message'] === 'boom'
            && is_array($event['trace']);
    });
});

test('frames outside vendor are marked as app frames', function () {
    $event = app(ExceptionReporter::class)->event(new RuntimeException('x'));

    expect($event['file'])->not->toStartWith('/')
        ->and(collect($event['trace'])->pluck('in_app')->unique()->all())->toContain(false);
});

test('a failing network never throws', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    app(ExceptionHandler::class)->report(new RuntimeException('boom'));

    expect(app(Client::class)->send([['type' => 'exception', 'class' => 'X']]))->toBeNull();
});

test('without a key nothing is sent', function () {
    config(['carabdheer.key' => null]);
    app()->forgetInstance(Client::class);
    app()->forgetInstance(ExceptionReporter::class);
    Http::fake();

    app(ExceptionHandler::class)->report(new RuntimeException('boom'));

    Http::assertNothingSent();
});

test('secret headers are filtered and the request is described', function () {
    $this->app->instance('request', Illuminate\Http\Request::create('https://shop.test/invoices/7?x=1', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer secret', 'HTTP_COOKIE' => 'a=b', 'HTTP_ACCEPT' => 'text/html']));

    $request = app(ExceptionReporter::class)->event(new RuntimeException('x'))['request'];

    expect($request['method'])->toBe('POST')
        ->and($request['url'])->toBe('https://shop.test/invoices/7?x=1')
        ->and($request['headers']['authorization'])->toBe('[filtered]')
        ->and($request['headers']['cookie'])->toBe('[filtered]')
        ->and($request['headers']['accept'])->toBe('text/html');
});

test('carabdheer:install sends a test event and says it was heard', function () {
    Http::fake(['carabdheer.test/*' => Http::response(['accepted' => 1], 202)]);

    $this->artisan('carabdheer:install')
        ->expectsOutputToContain('Carabdheer heard the test event')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => str_contains($request['events'][0]['message'], 'carabdheer:install'));
});

test('carabdheer:install says when the key is wrong', function () {
    Http::fake(['carabdheer.test/*' => Http::response(['message' => 'Unknown or revoked key.'], 401)]);

    $this->artisan('carabdheer:install')->expectsOutputToContain('answered 401')->assertFailed();
});
