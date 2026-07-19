<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsWeeklyPostMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $iso_year
 * @property int $iso_week
 * @property CarbonInterface $week_starts_on
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
    'iso_year', 'iso_week', 'week_starts_on', 'post_id', 'content_key', 'readers',
    'meaningful_readers', 'actioning_readers', 'sessions', 'page_views', 'article_progress_25',
    'article_progress_50', 'article_progress_75', 'article_progress_90', 'article_engaged',
    'select_content', 'shares', 'sign_ups', 'comment_submits', 'outbound_clicks',
    'file_downloads', 'synced_at',
])]
class AnalyticsWeeklyPostMetric extends Model
{
    /** @use HasFactory<AnalyticsWeeklyPostMetricFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsWeeklyPostMetricFactory
    {
        return AnalyticsWeeklyPostMetricFactory::new();
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
        return ['week_starts_on' => 'date', 'synced_at' => 'datetime'];
    }
}
