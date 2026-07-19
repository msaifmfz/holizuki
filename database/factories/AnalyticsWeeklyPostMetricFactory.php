<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsWeeklyPostMetric;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsWeeklyPostMetric> */
class AnalyticsWeeklyPostMetricFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsWeeklyPostMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = now()->startOfWeek();

        return [
            'iso_year' => $date->isoWeekYear, 'iso_week' => $date->isoWeek, 'week_starts_on' => $date,
            'post_id' => Post::factory(), 'content_key' => 'post:'.fake()->unique()->numberBetween(1000, 999999),
            'readers' => 100, 'meaningful_readers' => 40, 'actioning_readers' => 20, 'sessions' => 120,
            'page_views' => 150, 'article_progress_25' => 80, 'article_progress_50' => 60,
            'article_progress_75' => 40, 'article_progress_90' => 30, 'article_engaged' => 40,
            'select_content' => 14, 'shares' => 6, 'sign_ups' => 2, 'comment_submits' => 1,
            'outbound_clicks' => 4, 'file_downloads' => 1, 'synced_at' => now(),
        ];
    }
}
