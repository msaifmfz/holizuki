<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Identity\Actions\ResetUserPassword;
use App\Http\Auth\Actions\RegisterReader;
use App\Http\Auth\Responses\RoleAwareLoginResponse;
use App\Http\Auth\Responses\RoleAwareRedirectAsIntended;
use App\Http\Auth\Responses\RoleAwareRegisterResponse;
use App\Http\Auth\Responses\RoleAwareTwoFactorLoginResponse;
use App\Http\Auth\Responses\RoleAwareVerifyEmailResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\RedirectAsIntended;
use Override;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(LoginResponse::class, RoleAwareLoginResponse::class);
        $this->app->singleton(RegisterResponse::class, RoleAwareRegisterResponse::class);
        $this->app->singleton(TwoFactorLoginResponse::class, RoleAwareTwoFactorLoginResponse::class);
        $this->app->singleton(VerifyEmailResponse::class, RoleAwareVerifyEmailResponse::class);
        $this->app->bind(RedirectAsIntended::class, RoleAwareRedirectAsIntended::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::createUsersUsing(RegisterReader::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'returnTo' => $request->query('return_to'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn (Request $request) => Inertia::render('auth/register', [
            'returnTo' => $request->query('return_to'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('login', function (Request $request) {
            $username = Str::transliterate($request->string(Fortify::username())->toString());
            $throttleKey = Str::lower($username).'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->string('credential.id')->toString();
            $identifier = $credentialId !== '' ? $credentialId : $request->session()->getId();

            return Limit::perMinute(10)->by(
                $identifier.'|'.$request->ip(),
            );
        });

    }
}
