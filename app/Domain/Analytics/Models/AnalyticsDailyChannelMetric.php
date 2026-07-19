<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnalyticsDailyChannelMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property CarbonInterface $metric_date
 * @property string $channel
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
    'metric_date', 'channel', 'readers', 'meaningful_readers', 'actioning_readers', 'sessions',
    'page_views', 'article_progress_25', 'article_progress_50', 'article_progress_75',
    'article_progress_90', 'article_engaged', 'select_content', 'shares', 'sign_ups',
    'comment_submits', 'outbound_clicks', 'file_downloads', 'synced_at',
])]
class AnalyticsDailyChannelMetric extends Model
{
    /** @use HasFactory<AnalyticsDailyChannelMetricFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsDailyChannelMetricFactory
    {
        return AnalyticsDailyChannelMetricFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['metric_date' => 'date', 'synced_at' => 'datetime'];
    }
}
