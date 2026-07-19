<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnalyticsPeriodSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $scope_type
 * @property string $scope_key
 * @property string $period_key
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
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
 * @property int|null $previous_readers
 * @property int|null $previous_meaningful_readers
 * @property int|null $previous_actioning_readers
 * @property int|null $previous_page_views
 * @property int|null $previous_select_content
 * @property int|null $previous_shares
 * @property int|null $previous_sign_ups
 * @property int|null $previous_comment_submits
 * @property string $source
 * @property CarbonInterface $synced_at
 */
#[Fillable([
    'scope_type', 'scope_key', 'period_key', 'starts_on', 'ends_on', 'comparison_starts_on',
    'comparison_ends_on', 'readers', 'meaningful_readers', 'actioning_readers', 'sessions',
    'page_views', 'article_progress_25', 'article_progress_50', 'article_progress_75',
    'article_progress_90', 'article_engaged', 'select_content', 'shares', 'sign_ups',
    'comment_submits', 'outbound_clicks', 'file_downloads', 'previous_readers',
    'previous_meaningful_readers', 'previous_actioning_readers', 'previous_page_views',
    'previous_select_content', 'previous_shares', 'previous_sign_ups', 'previous_comment_submits',
    'source', 'synced_at',
])]
class AnalyticsPeriodSnapshot extends Model
{
    /** @use HasFactory<AnalyticsPeriodSnapshotFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsPeriodSnapshotFactory
    {
        return AnalyticsPeriodSnapshotFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'comparison_starts_on' => 'date',
            'comparison_ends_on' => 'date',
            'synced_at' => 'datetime',
        ];
    }
}
