<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AnalyticsDailyChannelMetric;
use App\Domain\Analytics\Models\AnalyticsDailyPostMetric;
use App\Domain\Analytics\Models\AnalyticsDailySiteMetric;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsUnmappedPath;
use App\Domain\Analytics\Models\AnalyticsWeeklyPostMetric;
use App\Domain\Analytics\Models\AnalyticsWeeklySiteMetric;
use App\Domain\Analytics\ValueObjects\AnalyticsMetricColumns;
use App\Domain\Analytics\ValueObjects\AnalyticsSyncPayload;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class PersistAnalyticsSyncPayload
{
    /** @var list<string> */
    private const array METRIC_COLUMNS = [...AnalyticsMetricColumns::NAMES, 'synced_at', 'updated_at'];

    public function handle(
        AnalyticsSyncPayload $payload,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
    ): void {
        DB::transaction(function () use ($payload, $startsOn, $endsOn): void {
            AnalyticsDailySiteMetric::query()->upsert(
                $payload->dailySite,
                ['metric_date'],
                self::METRIC_COLUMNS,
            );

            AnalyticsDailyPostMetric::query()
                ->whereBetween('metric_date', [$startsOn->toDateString(), $endsOn->toDateString()])
                ->delete();
            if ($payload->dailyPosts !== []) {
                AnalyticsDailyPostMetric::query()->upsert(
                    $payload->dailyPosts,
                    ['metric_date', 'content_key'],
                    [...self::METRIC_COLUMNS, 'post_id'],
                );
            }

            AnalyticsDailyChannelMetric::query()
                ->whereBetween('metric_date', [$startsOn->toDateString(), $endsOn->toDateString()])
                ->delete();
            if ($payload->dailyChannels !== []) {
                AnalyticsDailyChannelMetric::query()->upsert(
                    $payload->dailyChannels,
                    ['metric_date', 'channel'],
                    self::METRIC_COLUMNS,
                );
            }

            $firstWeek = $startsOn->startOfWeek()->toDateString();
            $lastWeek = $endsOn->startOfWeek()->toDateString();
            AnalyticsWeeklySiteMetric::query()->whereBetween('week_starts_on', [$firstWeek, $lastWeek])->delete();
            AnalyticsWeeklyPostMetric::query()->whereBetween('week_starts_on', [$firstWeek, $lastWeek])->delete();

            if ($payload->weeklySite !== []) {
                AnalyticsWeeklySiteMetric::query()->upsert(
                    $payload->weeklySite,
                    ['iso_year', 'iso_week'],
                    [...self::METRIC_COLUMNS, 'week_starts_on'],
                );
            }
            if ($payload->weeklyPosts !== []) {
                AnalyticsWeeklyPostMetric::query()->upsert(
                    $payload->weeklyPosts,
                    ['iso_year', 'iso_week', 'content_key'],
                    [...self::METRIC_COLUMNS, 'week_starts_on', 'post_id'],
                );
            }

            $snapshotPeriods = [];
            foreach ($payload->snapshots as $snapshot) {
                $snapshotStartsOn = $snapshot['starts_on'] ?? null;
                $snapshotEndsOn = $snapshot['ends_on'] ?? null;
                if (! is_string($snapshotStartsOn) || ! is_string($snapshotEndsOn)) {
                    throw new UnexpectedValueException('Analytics snapshots require string date boundaries.');
                }

                $snapshotPeriods[$snapshotStartsOn.'|'.$snapshotEndsOn] = [$snapshotStartsOn, $snapshotEndsOn];
            }
            foreach ($snapshotPeriods as [$snapshotStartsOn, $snapshotEndsOn]) {
                AnalyticsPeriodSnapshot::query()
                    ->whereDate('starts_on', $snapshotStartsOn)
                    ->whereDate('ends_on', $snapshotEndsOn)
                    ->delete();
            }

            if ($payload->snapshots !== []) {
                AnalyticsPeriodSnapshot::query()->upsert(
                    $payload->snapshots,
                    ['scope_key', 'starts_on', 'ends_on'],
                    [
                        'scope_type', 'period_key', 'comparison_starts_on', 'comparison_ends_on',
                        ...self::METRIC_COLUMNS,
                        'previous_readers', 'previous_meaningful_readers', 'previous_actioning_readers',
                        'previous_page_views', 'previous_select_content', 'previous_shares',
                        'previous_sign_ups', 'previous_comment_submits', 'source',
                    ],
                );
            }

            foreach ($payload->unmappedPaths as $row) {
                $path = $row['path'] ?? null;
                $readers = $row['readers'] ?? null;
                $pageViews = $row['page_views'] ?? null;
                if (! is_string($path) || ! is_int($readers) || ! is_int($pageViews)) {
                    throw new UnexpectedValueException('Unmapped analytics paths require a path and integer metrics.');
                }

                $unmapped = AnalyticsUnmappedPath::query()->firstOrNew(['path' => $path]);
                $unmapped->readers = max($unmapped->readers, $readers);
                $unmapped->page_views = max($unmapped->page_views, $pageViews);
                $unmapped->first_seen_at ??= now();
                $unmapped->last_seen_at = now();
                $unmapped->save();
            }
        });
    }
}
