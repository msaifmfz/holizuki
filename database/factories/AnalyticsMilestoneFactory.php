<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsMilestone> */
class AnalyticsMilestoneFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsMilestone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'code' => fake()->unique()->slug(2), 'scope_key' => 'site', 'evidence' => [], 'achieved_at' => now()];
    }
}
