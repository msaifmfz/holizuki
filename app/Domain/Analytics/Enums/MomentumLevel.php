<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

enum MomentumLevel: string
{
    case Starting = 'starting';
    case Building = 'building';
    case Growing = 'growing';
    case Compounding = 'compounding';

    public static function forScore(int $score): self
    {
        return match (true) {
            $score <= 24 => self::Starting,
            $score <= 49 => self::Building,
            $score <= 74 => self::Growing,
            default => self::Compounding,
        };
    }
}
