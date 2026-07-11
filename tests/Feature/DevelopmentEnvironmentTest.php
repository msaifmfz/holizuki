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

test('devbox defines the canonical local environment and workflows', function (): void {
    $devbox = json_decode(
        file_get_contents(base_path('devbox.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($devbox['env']['PGPORT'])->toBe('54320')
        ->and($devbox['shell']['scripts'])->toHaveKeys([
            'setup',
            'dev',
            'doctor',
            'check',
            'test',
            'test:browser',
            'services:stop',
        ])
        ->and($devbox['shell']['scripts']['setup'])->toBe('bash devbox.d/setup.sh')
        ->and($devbox['shell']['scripts']['dev'])->toBe('bash devbox.d/dev.sh')
        ->and($devbox['shell']['scripts']['doctor'])->toBe('bash devbox.d/doctor.sh')
        ->and($composer['scripts']['setup'])->toContain(
            'DEVBOX_USE_VERSION=0.17.5 devbox run setup',
        );
});
