<?php

declare(strict_types=1);

return [
    'proxies' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', ''))),
        static fn (string $proxy): bool => $proxy !== '',
    )),
];
