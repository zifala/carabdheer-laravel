<?php

declare(strict_types=1);

use Carabdheer\Carabdheer\Carabdheer;

it('resolves the singleton', function () {
    expect(app(Carabdheer::class))->toBeInstanceOf(Carabdheer::class);
});

it('returns the same instance from the container', function () {
    expect(app(Carabdheer::class))->toBe(app(Carabdheer::class));
});

it('merges the package config', function () {
    expect(config('carabdheer-laravel.placeholder'))->toBe('default');
});

it('registers the artisan command', function () {
    $this->artisan('carabdheer-laravel:placeholder')
        ->expectsOutputToContain('Carabdheer placeholder command executed.')
        ->assertSuccessful();
});
