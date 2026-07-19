<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Models\User;
use App\Domain\Reading\Support\ReaderIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('serve --host=localhost --port=8000 --tries=1', 'server');

        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureErrorPages();
        $this->configureSsr();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands(
            $this->app->isProduction(),
        );

        Password::defaults(fn (): ?Password => $this->app->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureAuthorization(): void
    {
        Gate::before(static fn (User $user): ?bool => $user->isAdministrator() ? true : null);
    }

    /**
     * Only public pages benefit from server-side rendering; authenticated
     * pages are crawler-invisible and the post editor (TipTap) cannot render
     * on the server.
     */
    private function configureSsr(): void
    {
        Inertia::disableSsr(static fn (): bool => request()->user() !== null);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('contact', static fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->ip()));
        RateLimiter::for('post-views', static fn (Request $request): Limit => Limit::perMinute(30)->by(ReaderIdentity::limiterKey($request)));
        RateLimiter::for('newsletter', static fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->ip()));
        RateLimiter::for('comments', static fn (Request $request): Limit => Limit::perHour(5)->by((string) ($request->user()->id ?? $request->ip())));
        RateLimiter::for('analytics-realtime', static fn (Request $request): Limit => Limit::perMinute(10)->by((string) ($request->user()->id ?? $request->ip())));
    }

    /**
     * Render styled Inertia error pages instead of the framework defaults.
     *
     * 403/404 always render the error page; 500/503 keep the framework's
     * debug output in local and testing environments.
     */
    private function configureErrorPages(): void
    {
        Inertia::handleExceptionsUsing(function (ExceptionResponse $response): ?ExceptionResponse {
            if ($response->request->expectsJson()) {
                return null;
            }

            $status = $response->statusCode();
            $always = in_array($status, [403, 404], true);
            $productionOnly = in_array($status, [500, 503], true) && ! $this->app->environment(['local', 'testing']);

            if ($always || $productionOnly) {
                return $response->render('error', ['status' => $status])->withSharedData();
            }

            return null;
        });
    }
}
