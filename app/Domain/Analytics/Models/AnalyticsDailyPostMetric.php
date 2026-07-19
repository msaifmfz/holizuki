<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsDailyPostMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property CarbonInterface $metric_date
 * @property int|null $post_id
 * @property string $content_key
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
    'metric_date', 'post_id', 'content_key', 'readers', 'meaningful_readers', 'actioning_readers',
    'sessions', 'page_views', 'article_progress_25', 'article_progress_50', 'article_progress_75',
    'article_progress_90', 'article_engaged', 'select_content', 'shares', 'sign_ups', 'comment_submits',
    'outbound_clicks', 'file_downloads', 'synced_at',
])]
class AnalyticsDailyPostMetric extends Model
{
    /** @use HasFactory<AnalyticsDailyPostMetricFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsDailyPostMetricFactory
    {
        return AnalyticsDailyPostMetricFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['metric_date' => 'date', 'synced_at' => 'datetime'];
    }
}
