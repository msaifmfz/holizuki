<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AnalyticsPeriodSnapshot> */
class AnalyticsPeriodSnapshotFactory extends Factory
{
    #[Override]
    protected $model = AnalyticsPeriodSnapshot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'scope_type' => 'site', 'scope_key' => 'site', 'period_key' => '28d',
            'starts_on' => now()->subDays(27)->toDateString(), 'ends_on' => now()->toDateString(),
            'comparison_starts_on' => now()->subDays(55)->toDateString(), 'comparison_ends_on' => now()->subDays(28)->toDateString(),
            'readers' => 500, 'meaningful_readers' => 200, 'actioning_readers' => 100, 'sessions' => 600,
            'page_views' => 750, 'article_progress_25' => 400, 'article_progress_50' => 300,
            'article_progress_75' => 200, 'article_progress_90' => 150, 'article_engaged' => 200,
            'select_content' => 70, 'shares' => 30, 'sign_ups' => 10, 'comment_submits' => 5,
            'outbound_clicks' => 20, 'file_downloads' => 3, 'previous_readers' => 450,
            'previous_meaningful_readers' => 175, 'previous_actioning_readers' => 80,
            'previous_page_views' => 700, 'previous_select_content' => 60, 'previous_shares' => 25,
            'previous_sign_ups' => 8, 'previous_comment_submits' => 4, 'source' => 'exact', 'synced_at' => now(),
        ];
    }
}
