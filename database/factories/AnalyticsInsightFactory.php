<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Enums\InsightConfidence;
use App\Domain\Analytics\Enums\InsightStatus;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsInsight> */
class AnalyticsInsightFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsInsight::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'post_id' => Post::factory()->published(), 'rule_id' => 'improve_introduction', 'scope_key' => 'post:'.fake()->unique()->numberBetween(1000, 999999), 'confidence' => InsightConfidence::Medium, 'status' => InsightStatus::Active, 'evidence' => ['readers' => 100], 'observation' => 'Fewer readers reach the first quarter than comparable posts.', 'suggested_action' => 'Make the opening promise clearer.', 'dismissal_reason' => null, 'detected_at' => now(), 'last_seen_at' => now(), 'dismissed_until' => null, 'completed_at' => null];
    }
}
