<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAuthorPortalAccess;
use App\Http\Middleware\EnsureReadersUsePublicAccountSurface;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\ReadinessController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::get('ready', ReadinessController::class)->name('ready');
        },
    )
    ->withCommands([
        __DIR__.'/../app/Domain/Identity/Console',
        __DIR__.'/../app/Domain/Community/Console',
        __DIR__.'/../app/Domain/Analytics/Console',
        __DIR__.'/../app/Domain/Publishing/Console',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->trustHosts(
            at: static fn (): array => array_values(array_filter(
                config()->array('deployment.trusted_hosts'),
                is_string(...),
            )),
            subdomains: false,
        );

        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(append: [
            HandleAppearance::class,
            EnsureReadersUsePublicAccountSurface::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'access-author-portal' => EnsureAuthorPortalAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
