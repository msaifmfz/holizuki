<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use Carbon\CarbonImmutable;

class FinalizeGoalPeriods
{
    public function handle(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today(config()->string('app.timezone'));
        $finalized = 0;

        AuthorGoalPeriod::query()
            ->whereIn('status', [GoalPeriodStatus::Scheduled, GoalPeriodStatus::Active])
            ->whereDate('ends_on', '<', $today->toDateString())
            ->orderBy('id')
            ->each(function (AuthorGoalPeriod $period) use (&$finalized): void {
                $period->update([
                    'status' => $period->hasMetTarget() ? GoalPeriodStatus::Met : GoalPeriodStatus::Missed,
                    'finalized_at' => now(),
                ]);
                $finalized++;
            });

        return $finalized;
    }
}
