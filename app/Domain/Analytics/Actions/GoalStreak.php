<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;

class GoalStreak
{
    public function handle(User $user): int
    {
        $streak = 0;
        $periods = AuthorGoalPeriod::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [GoalPeriodStatus::Met, GoalPeriodStatus::Missed, GoalPeriodStatus::Paused])
            ->latest('ends_on')
            ->get();

        foreach ($periods as $period) {
            if ($period->status === GoalPeriodStatus::Paused) {
                continue;
            }

            if ($period->status === GoalPeriodStatus::Missed) {
                break;
            }

            $streak++;
        }

        return $streak;
    }
}
