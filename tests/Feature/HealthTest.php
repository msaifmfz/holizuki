<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\DB;

test('liveness reports that the application booted', function (): void {
    $this->get('/up')->assertOk();
});

test('trusted hosts are resolved from deployment configuration', function (): void {
    config()->set('deployment.trusted_hosts', ['^holizuki\.test$']);

    expect(resolve(TrustHosts::class)->hosts())->toBe(['^holizuki\.test$']);
});

test('readiness reports that application dependencies are available', function (): void {
    config()->set('deployment.release', 'v1.2.3-rc.1');

    $this->get(route('ready'))
        ->assertNoContent()
        ->assertHeader('X-Holizuki-Release', 'v1.2.3-rc.1');
});

test('readiness is unavailable when the database cannot be reached', function (): void {
    $defaultConnection = config('database.default');

    config()->set('database.default', 'unavailable');
    config()->set('database.connections.unavailable', [
        'driver' => 'sqlite',
        'database' => storage_path('missing/database.sqlite'),
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);

    DB::purge();

    $response = $this->get(route('ready'));

    config()->set('database.default', $defaultConnection);
    DB::purge('unavailable');

    $response->assertServiceUnavailable();
});
