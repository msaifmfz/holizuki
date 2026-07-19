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

class ResumeGoalPeriod
{
    public function handle(User $user, AuthorGoalPeriod $period): AuthorGoalPeriod
    {
        if ($period->user_id !== $user->id || $period->status !== GoalPeriodStatus::Paused) {
            throw new InvalidArgumentException('Only your own paused goal periods can be resumed.');
        }

        $today = CarbonImmutable::today(config()->string('app.timezone'));

        return DB::transaction(function () use ($period, $today): AuthorGoalPeriod {
            AuthorGoalPause::query()
                ->where('user_id', $period->user_id)
                ->whereDate('starts_on', '<=', $period->starts_on->toDateString())
                ->whereDate('ends_on', '>=', $period->ends_on->toDateString())
                ->delete();

            $ended = $period->ends_on->isBefore($today);
            $status = match (true) {
                $ended && $period->published_count >= $period->target => GoalPeriodStatus::Met,
                $ended => GoalPeriodStatus::Missed,
                $period->starts_on->isAfter($today) => GoalPeriodStatus::Scheduled,
                default => GoalPeriodStatus::Active,
            };

            $period->update([
                'status' => $status,
                'finalized_at' => $ended ? now() : null,
            ]);

            return $period->refresh();
        });
    }
}
