<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnalyticsDailySiteMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property CarbonInterface $metric_date
 * @property int $readers
 * @property int $meaningful_readers
 * @property int $actioning_readers
 * @property int $sessions
 * @property int $page_views
 * @property int $article_progress_25
 * @property int $article_progress_50
 * @property int $article_progress_75
 * @property int $article_progress_90
 * @property int $article_engaged
 * @property int $select_content
 * @property int $shares
 * @property int $sign_ups
 * @property int $comment_submits
 * @property int $outbound_clicks
 * @property int $file_downloads
 * @property CarbonInterface $synced_at
 */
#[Fillable([
    'metric_date', 'readers', 'meaningful_readers', 'actioning_readers', 'sessions', 'page_views',
    'article_progress_25', 'article_progress_50', 'article_progress_75', 'article_progress_90',
    'article_engaged', 'select_content', 'shares', 'sign_ups', 'comment_submits', 'outbound_clicks',
    'file_downloads', 'synced_at',
])]
class AnalyticsDailySiteMetric extends Model
{
    /** @use HasFactory<AnalyticsDailySiteMetricFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsDailySiteMetricFactory
    {
        return AnalyticsDailySiteMetricFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['metric_date' => 'date', 'synced_at' => 'datetime'];
    }
}
