<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureReadersUsePublicAccountSurface
{
    /** @var list<string> */
    private const array RESTRICTED_PATHS = [
        'settings',
        'settings/*',
        'user/confirm-password',
        'user/confirmed-password-status',
        'user/passkeys',
        'user/passkeys/*',
        'user/password',
        'user/profile-information',
        'user/two-factor-authentication',
        'user/confirmed-two-factor-authentication',
        'user/two-factor-qr-code',
        'user/two-factor-recovery-codes',
    ];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isReader() && Str::is(self::RESTRICTED_PATHS, $request->path())) {
            abort(403);
        }

        return $next($request);
    }
}
