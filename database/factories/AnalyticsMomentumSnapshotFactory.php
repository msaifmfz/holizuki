<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\MomentumLevel;
use App\Domain\Analytics\Models\AnalyticsMomentumSnapshot;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsMomentumSnapshot> */
class AnalyticsMomentumSnapshotFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsMomentumSnapshot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'scored_on' => now(), 'score' => 50, 'level' => MomentumLevel::Growing, 'components' => [], 'freshness' => FreshnessState::Fresh, 'data_freshness_at' => now(), 'calculated_at' => now()];
    }
}
