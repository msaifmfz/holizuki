<?php

declare(strict_types=1);

namespace App\Http\Auth\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicReturnPath
{
    /** @var list<string> */
    private const array EXACT_PATHS = [
        '/', '/about', '/archive', '/contact', '/privacy', '/search', '/terms', '/topics',
    ];

    /** @var list<string> */
    private const array PATH_PATTERNS = [
        '/archive/*', '/authors/*', '/categories/*', '/posts/*', '/tags/*',
    ];

    public function resolve(Request $request): string
    {
        $requested = $request->input('return_to');
        $candidate = is_string($requested) && $requested !== ''
            ? $requested
            : $request->session()->pull('url.intended');

        return is_string($candidate) ? ($this->normalize($candidate) ?? '/') : '/';
    }

    public function normalize(string $candidate): ?string
    {
        $parts = parse_url($candidate);
        $appParts = parse_url(config()->string('app.url'));

        if ($parts === false || $appParts === false) {
            return null;
        }

        if (isset($parts['host'])) {
            $sameHost = Str::lower($parts['host']) === Str::lower($appParts['host'] ?? '');
            $sameScheme = ! isset($parts['scheme']) || Str::lower($parts['scheme']) === Str::lower($appParts['scheme'] ?? '');
            $samePort = ($parts['port'] ?? null) === ($appParts['port'] ?? null);

            if (! $sameHost || ! $sameScheme || ! $samePort) {
                return null;
            }
        }

        $path = '/'.ltrim($parts['path'] ?? '/', '/');
        $isAllowed = in_array($path, self::EXACT_PATHS, true)
            || collect(self::PATH_PATTERNS)->contains(fn (string $pattern): bool => Str::is($pattern, $path));

        if (! $isAllowed || Str::contains($path, ["\0", "\r", "\n"])) {
            return null;
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment'])
            && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $parts['fragment']) === 1
                ? '#'.$parts['fragment']
                : '';

        return $path.$query.$fragment;
    }
}
