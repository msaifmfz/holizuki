<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AuthorGoalPeriod> */
class AuthorGoalPeriodFactory extends Factory
{
    #[Override]
    protected $model = AuthorGoalPeriod::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['goal_id' => AuthorGoal::factory(), 'user_id' => User::factory(), 'starts_on' => now()->startOfWeek(), 'ends_on' => now()->endOfWeek(), 'target' => 1, 'published_count' => 0, 'status' => GoalPeriodStatus::Active, 'finalized_at' => null];
    }
}
