<?php

declare(strict_types=1);

namespace App\Domain\Community\Support;

use Illuminate\Support\Str;
use RuntimeException;

class SubscriberIdentity
{
    public function normalize(string $email): string
    {
        return Str::of($email)->trim()->lower()->toString();
    }

    public function hash(string $email): string
    {
        $key = config('community.email_hash_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('The community email hash key is not configured.');
        }

        return hash_hmac('sha256', $this->normalize($email), $key);
    }

    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
