<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

class ReaderIdentity
{
    public static function limiterKey(Request $request): string
    {
        return hash('sha256', self::visitorIdentity($request));
    }

    public static function dailyHash(Request $request, string $date): string
    {
        return hash_hmac(
            'sha256',
            self::visitorIdentity($request).'|'.$date,
            config()->string('app.key'),
        );
    }

    /**
     * The IP is part of the identity so clients that drop cookies (and get a
     * fresh session per request) cannot mint unlimited view hashes or
     * rate-limit buckets. The HMAC keeps the stored value anonymized.
     */
    private static function visitorIdentity(Request $request): string
    {
        return 'session:'.$request->session()->getId().'|ip:'.$request->ip();
    }
}
