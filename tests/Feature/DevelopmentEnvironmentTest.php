<?php

declare(strict_types=1);

test('the development server uses the canonical local origin', function (): void {
    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');

    $this->artisan('dev:list')
        ->expectsOutputToContain('php artisan serve --host=localhost --port=8000 --tries=1')
        ->assertExitCode(0);
});
