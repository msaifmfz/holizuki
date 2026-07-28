<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

final class SafeExternalSourceUrl implements ValidationRule
{
    /** @var list<string> */
    private const array NON_PUBLIC_IP_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    ];

    /**
     * This rejects obvious local destinations at intake. Any future network
     * client must resolve and re-check every destination IP at request time.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The source URL must be a valid public HTTP or HTTPS URL.');

            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts)
            || ! in_array(Str::lower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])) {
            $fail('The source URL must be a valid public HTTP or HTTPS URL.');

            return;
        }

        $host = Str::of($parts['host'] ?? '')
            ->trim('[]')
            ->trim('.')
            ->lower()
            ->toString();

        if ($host === ''
            || $this->isLocalHostname($host)
            || (! $this->isPublicIp($host) && ! $this->isHostname($host))) {
            $fail('The source URL must point to a public host.');
        }
    }

    private function isLocalHostname(string $host): bool
    {
        return $host === 'localhost'
            || Str::endsWith($host, ['.localhost', '.local', '.internal', '.home', '.lan']);
    }

    private function isPublicIp(string $host): bool
    {
        if (inet_pton($host) === false) {
            return false;
        }

        return ! IpUtils::checkIp($host, self::NON_PUBLIC_IP_RANGES);
    }

    private function isHostname(string $host): bool
    {
        if (strlen($host) > 253
            || ! str_contains($host, '.')
            || preg_match('/\A[0-9.]+\z/', $host) === 1
            || preg_match('/\A(?:0x[0-9a-f]+|[0-9]+)(?:\.(?:0x[0-9a-f]+|[0-9]+))*\z/i', $host) === 1) {
            return false;
        }

        return array_all(explode('.', $host), fn (string $label): bool => preg_match('/\A(?!-)[a-z0-9-]{1,63}(?<!-)\z/i', $label) === 1);
    }
}
