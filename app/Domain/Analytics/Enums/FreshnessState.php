<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

use Carbon\CarbonInterface;

enum FreshnessState: string
{
    case Fresh = 'fresh';
    case Delayed = 'delayed';
    case Stale = 'stale';
    case Unavailable = 'unavailable';

    public static function forLastSuccess(?CarbonInterface $lastSuccess): self
    {
        if (! $lastSuccess instanceof CarbonInterface) {
            return self::Unavailable;
        }

        $seconds = $lastSuccess->diffInSeconds(now());

        return match (true) {
            $seconds <= 8 * 60 * 60 => self::Fresh,
            $seconds <= 24 * 60 * 60 => self::Delayed,
            default => self::Stale,
        };
    }
}
