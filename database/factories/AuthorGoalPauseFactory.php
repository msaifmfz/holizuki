<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AuthorGoalPause;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AuthorGoalPause> */
class AuthorGoalPauseFactory extends Factory
{
    #[Override]
    protected $model = AuthorGoalPause::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'starts_on' => now()->startOfWeek(), 'ends_on' => now()->endOfWeek(), 'reason' => null];
    }
}
