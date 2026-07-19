<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoalPause;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PauseGoalPeriod
{
    public function handle(User $user, AuthorGoalPeriod $period, ?string $reason = null): AuthorGoalPeriod
    {
        $today = CarbonImmutable::today(config()->string('app.timezone'));

        if ($period->user_id !== $user->id || $period->ends_on->isBefore($today)) {
            throw new InvalidArgumentException('Only complete current or future goal periods can be paused.');
        }

        return DB::transaction(function () use ($user, $period, $reason): AuthorGoalPeriod {
            if ($period->published_count >= $period->target) {
                $period->update(['status' => GoalPeriodStatus::Met, 'finalized_at' => now()]);

                return $period->refresh();
            }

            AuthorGoalPause::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'starts_on' => $period->starts_on->startOfDay(),
                    'ends_on' => $period->ends_on->startOfDay(),
                ],
                ['reason' => $reason],
            );
            $period->update(['status' => GoalPeriodStatus::Paused, 'finalized_at' => now()]);

            return $period->refresh();
        });
    }
}
