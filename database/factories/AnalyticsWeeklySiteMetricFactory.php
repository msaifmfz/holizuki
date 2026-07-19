<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsWeeklySiteMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsWeeklySiteMetric> */
class AnalyticsWeeklySiteMetricFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsWeeklySiteMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $date = now()->startOfWeek();

        return [
            'iso_year' => $date->isoWeekYear, 'iso_week' => $date->isoWeek, 'week_starts_on' => $date,
            'readers' => 500, 'meaningful_readers' => 200, 'actioning_readers' => 100, 'sessions' => 600,
            'page_views' => 750, 'article_progress_25' => 400, 'article_progress_50' => 300,
            'article_progress_75' => 200, 'article_progress_90' => 150, 'article_engaged' => 200,
            'select_content' => 70, 'shares' => 30, 'sign_ups' => 10, 'comment_submits' => 5,
            'outbound_clicks' => 20, 'file_downloads' => 3, 'synced_at' => now(),
        ];
    }
}
