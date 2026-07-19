<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsDailySiteMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsDailySiteMetric> */
class AnalyticsDailySiteMetricFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsDailySiteMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return array_merge($this->metrics(), ['metric_date' => fake()->unique()->date(), 'synced_at' => now()]);
    }

    /** @return array<string, int> */
    private function metrics(): array
    {
        return [
            'readers' => 100, 'meaningful_readers' => 45, 'actioning_readers' => 20,
            'sessions' => 120, 'page_views' => 160, 'article_progress_25' => 80,
            'article_progress_50' => 60, 'article_progress_75' => 45, 'article_progress_90' => 30,
            'article_engaged' => 45, 'select_content' => 12, 'shares' => 5, 'sign_ups' => 2,
            'comment_submits' => 1, 'outbound_clicks' => 4, 'file_downloads' => 1,
        ];
    }
}
