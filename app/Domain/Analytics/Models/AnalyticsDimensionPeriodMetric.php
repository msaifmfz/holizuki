<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnalyticsDimensionPeriodMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $dimension_type
 * @property string $dimension_value
 * @property int $position
 * @property string $period_key
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property int $readers
 * @property int $page_views
 * @property int|null $previous_readers
 * @property int|null $previous_page_views
 * @property CarbonInterface $synced_at
 */
#[Fillable([
    'dimension_type', 'dimension_value', 'position', 'period_key', 'starts_on', 'ends_on',
    'readers', 'page_views', 'previous_readers', 'previous_page_views', 'synced_at',
])]
class AnalyticsDimensionPeriodMetric extends Model
{
    /** @use HasFactory<AnalyticsDimensionPeriodMetricFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsDimensionPeriodMetricFactory
    {
        return AnalyticsDimensionPeriodMetricFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
