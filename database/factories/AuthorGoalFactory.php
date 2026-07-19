<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\GoalCadence;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AuthorGoal> */
class AuthorGoalFactory extends Factory
{
    #[Override]
    protected $model = AuthorGoal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'cadence' => GoalCadence::Weekly, 'target' => 1, 'effective_from' => now()->startOfWeek(), 'effective_until' => null, 'disabled_at' => null];
    }
}
