<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

enum ErrorPagePortal: string
{
    case Admin = 'admin';
    case Public = 'public';

    private const string AUTHOR_PORTAL_MIDDLEWARE = 'access-author-portal';

    /** @var list<string> */
    private const array ADMIN_SETTINGS_PATHS = [
        'user',
        'user/*',
    ];

    public static function resolve(Request $request): self
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isAdministrator()) {
            return self::Public;
        }

        $route = $request->route();

        if (! $route instanceof Route) {
            return self::Public;
        }

        $isAdminRoute = in_array(self::AUTHOR_PORTAL_MIDDLEWARE, $route->gatherMiddleware(), true)
            || Str::is(self::ADMIN_SETTINGS_PATHS, $request->path());

        return $isAdminRoute ? self::Admin : self::Public;
    }
}
