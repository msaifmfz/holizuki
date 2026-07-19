<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\GoalCadence;
use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SetPublishingGoal
{
    public function __construct(
        private readonly MaterializeGoalPeriod $materialize,
        private readonly TrackAuthorProductEvent $trackEvent,
    ) {}

    public function handle(User $user, GoalCadence $cadence, int $target): AuthorGoal
    {
        $this->validateTarget($cadence, $target);
        $now = CarbonImmutable::now(config()->string('app.timezone'));
        $effectiveFrom = $cadence->nextBoundary($now);

        $goal = DB::transaction(function () use ($user, $cadence, $target, $effectiveFrom): AuthorGoal {
            AuthorGoal::query()
                ->where('user_id', $user->id)
                ->whereNull('effective_until')
                ->whereDate('effective_from', '<', $effectiveFrom->toDateString())
                ->update(['effective_until' => $effectiveFrom->subDay()->toDateString()]);

            $goal = AuthorGoal::query()
                ->where('user_id', $user->id)
                ->whereDate('effective_from', $effectiveFrom->toDateString())
                ->first() ?? new AuthorGoal([
                    'user_id' => $user->id,
                    'effective_from' => $effectiveFrom,
                ]);

            if ($goal->exists) {
                AuthorGoalPeriod::query()
                    ->where('goal_id', $goal->id)
                    ->where('status', GoalPeriodStatus::Scheduled)
                    ->where('published_count', 0)
                    ->delete();
            }

            $goal->fill([
                'cadence' => $cadence,
                'target' => $target,
                'effective_until' => null,
                'disabled_at' => null,
            ])->save();
            $this->materialize->handle($goal, $effectiveFrom);

            return $goal;
        });

        $this->trackEvent->handle($user, 'goal_set', $cadence->value, [
            'cadence' => $cadence->value,
            'target' => $target,
        ]);

        return $goal;
    }

    public function disable(User $user): ?AuthorGoal
    {
        $now = CarbonImmutable::now(config()->string('app.timezone'));
        $goal = AuthorGoal::query()
            ->where('user_id', $user->id)
            ->whereNull('effective_until')
            ->latest('effective_from')
            ->first();

        if ($goal === null) {
            return null;
        }

        $boundary = $goal->cadence->nextBoundary($now);
        if ($goal->effective_from->isFuture()) {
            AuthorGoalPeriod::query()
                ->where('goal_id', $goal->id)
                ->where('status', GoalPeriodStatus::Scheduled)
                ->where('published_count', 0)
                ->delete();
        }
        $goal->update([
            'effective_until' => $boundary->subDay()->toDateString(),
            'disabled_at' => $boundary,
        ]);
        $this->trackEvent->handle($user, 'goal_set', 'none', ['cadence' => 'none']);

        return $goal->refresh();
    }

    private function validateTarget(GoalCadence $cadence, int $target): void
    {
        $valid = match ($cadence) {
            GoalCadence::Weekly => $target >= 1 && $target <= 7,
            GoalCadence::Monthly => $target >= 1 && $target <= 31,
        };

        if (! $valid) {
            throw new InvalidArgumentException('The publishing goal target is outside the supported range.');
        }
    }
}
