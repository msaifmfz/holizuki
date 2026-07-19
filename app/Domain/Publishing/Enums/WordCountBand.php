<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

enum WordCountBand: string
{
    case Under500 = 'under_500';
    case From500To999 = '500_999';
    case From1000To1499 = '1000_1499';
    case From1500To2499 = '1500_2499';
    case From2500 = '2500_plus';

    public static function forWordCount(int $wordCount): self
    {
        return match (true) {
            $wordCount < 500 => self::Under500,
            $wordCount < 1000 => self::From500To999,
            $wordCount < 1500 => self::From1000To1499,
            $wordCount < 2500 => self::From1500To2499,
            default => self::From2500,
        };
    }
}
