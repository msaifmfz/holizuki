<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsDimensionPeriodMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsDimensionPeriodMetric> */
class AnalyticsDimensionPeriodMetricFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsDimensionPeriodMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'dimension_type' => 'country', 'dimension_value' => 'United States', 'position' => 1,
            'period_key' => '28d', 'starts_on' => now()->subDays(27)->toDateString(),
            'ends_on' => now()->toDateString(), 'readers' => 250, 'page_views' => 400,
            'previous_readers' => 200, 'previous_page_views' => 320, 'synced_at' => now(),
        ];
    }
}
