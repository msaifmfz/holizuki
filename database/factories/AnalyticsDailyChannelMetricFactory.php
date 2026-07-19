<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsDailyChannelMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsDailyChannelMetric> */
class AnalyticsDailyChannelMetricFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsDailyChannelMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'metric_date' => fake()->date(), 'channel' => fake()->randomElement(['Organic Search', 'Direct', 'Referral']),
            'readers' => 50, 'meaningful_readers' => 20, 'actioning_readers' => 10, 'sessions' => 60,
            'page_views' => 75, 'article_progress_25' => 40, 'article_progress_50' => 30,
            'article_progress_75' => 20, 'article_progress_90' => 15, 'article_engaged' => 20,
            'select_content' => 7, 'shares' => 3, 'sign_ups' => 1, 'comment_submits' => 1,
            'outbound_clicks' => 2, 'file_downloads' => 0, 'synced_at' => now(),
        ];
    }
}
