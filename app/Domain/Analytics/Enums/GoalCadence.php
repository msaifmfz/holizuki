<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

use Carbon\CarbonImmutable;

enum GoalCadence: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function nextBoundary(CarbonImmutable $now): CarbonImmutable
    {
        return match ($this) {
            self::Weekly => $now->startOfWeek()->addWeek()->startOfDay(),
            self::Monthly => $now->startOfMonth()->addMonth()->startOfDay(),
        };
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function periodContaining(CarbonImmutable $date): array
    {
        return match ($this) {
            self::Weekly => [$date->startOfWeek()->startOfDay(), $date->endOfWeek()->startOfDay()],
            self::Monthly => [$date->startOfMonth()->startOfDay(), $date->endOfMonth()->startOfDay()],
        };
    }
}
