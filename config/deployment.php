<?php

declare(strict_types=1);

/**
 * @return list<string>
 */
$splitValues = (static fn (string $value): array => array_values(array_filter(
    array_map(trim(...), explode(',', $value)),
    static fn (string $item): bool => $item !== '',
)));

$configuredTrustedHosts = $splitValues((string) env('TRUSTED_HOSTS', ''));
$applicationHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST);

if ($configuredTrustedHosts === [] && is_string($applicationHost)) {
    $configuredTrustedHosts = [$applicationHost];
}

$trustedHosts = array_map(
    static fn (string $host): string => '^'.preg_quote($host, '/').'$',
    $configuredTrustedHosts,
);

return [
    'release' => env('APP_RELEASE', 'local'),
    'trusted_hosts' => $trustedHosts,
];
