<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPause;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use Carbon\CarbonImmutable;

class MaterializeGoalPeriod
{
    public function handle(AuthorGoal $goal, CarbonImmutable $date): AuthorGoalPeriod
    {
        [$startsOn, $endsOn] = $goal->cadence->periodContaining($date);
        $today = CarbonImmutable::today(config()->string('app.timezone'));
        $isPaused = AuthorGoalPause::query()
            ->where('user_id', $goal->user_id)
            ->whereDate('starts_on', '<=', $startsOn->toDateString())
            ->whereDate('ends_on', '>=', $endsOn->toDateString())
            ->exists();

        $status = match (true) {
            $isPaused => GoalPeriodStatus::Paused,
            $endsOn->isBefore($today) => GoalPeriodStatus::Missed,
            $startsOn->isAfter($today) => GoalPeriodStatus::Scheduled,
            default => GoalPeriodStatus::Active,
        };

        return AuthorGoalPeriod::query()->firstOrCreate(
            [
                'user_id' => $goal->user_id,
                'starts_on' => $startsOn->startOfDay(),
                'ends_on' => $endsOn->startOfDay(),
            ],
            [
                'goal_id' => $goal->id,
                'target' => $goal->target,
                'published_count' => 0,
                'status' => $status,
                'finalized_at' => $endsOn->isBefore($today) ? now() : null,
            ],
        );
    }
}
