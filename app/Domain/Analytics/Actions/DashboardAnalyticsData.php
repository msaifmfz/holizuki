<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Models\AnalyticsDailySiteMetric;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AnalyticsWeeklySiteMetric;
use App\Domain\Analytics\ValueObjects\DashboardPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DashboardAnalyticsData
{
    /** @return array<string, mixed> */
    public function handle(DashboardPeriod $period): array
    {
        $snapshot = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->whereDate('starts_on', $period->startsOn->toDateString())
            ->whereDate('ends_on', $period->endsOn->toDateString())
            ->first();

        // Standard windows always end today, so between midnight and the next
        // sync no exact snapshot exists yet; showing yesterday's completed
        // window (flagged as a fallback) beats a wall of empty metrics.
        $isFallbackWindow = false;
        if ($snapshot === null && $period->key !== 'custom') {
            $snapshot = AnalyticsPeriodSnapshot::query()
                ->where('scope_key', 'site')
                ->where('period_key', $period->key)
                ->latest('ends_on')
                ->first();
            $isFallbackWindow = $snapshot !== null;
        }

        $lastSuccess = AnalyticsSyncRun::query()
            ->where('status', 'succeeded')
            ->latest('completed_at')
            ->value('completed_at');
        $lastSuccessAt = $lastSuccess instanceof CarbonInterface
            ? $lastSuccess
            : (is_string($lastSuccess) ? CarbonImmutable::parse($lastSuccess) : null);
        $freshness = FreshnessState::forLastSuccess($lastSuccessAt);
        if ($isFallbackWindow && $freshness === FreshnessState::Fresh) {
            $freshness = FreshnessState::Delayed;
        }

        return [
            'enabled' => config()->boolean('analytics.dashboard_enabled'),
            'period' => [
                'key' => $period->key,
                'from' => $period->startsOn->toDateString(),
                'to' => $period->endsOn->toDateString(),
                'days' => $period->days(),
            ],
            'freshness' => [
                'state' => $freshness->value,
                'refreshedAt' => $lastSuccessAt?->toISOString(),
            ],
            'metrics' => [
                'readers' => $this->metric(
                    $snapshot?->readers,
                    $snapshot?->previous_readers,
                    'Active users measured after analytics consent. This value is exact for the selected range.',
                    $freshness,
                ),
                'meaningfulReaders' => $this->metric(
                    $snapshot?->meaningful_readers,
                    $snapshot?->previous_meaningful_readers,
                    'Measured readers who reached 50% and spent 30 active seconds with the article.',
                    $freshness,
                ),
                'readerActionRate' => $this->rateMetric(
                    $snapshot?->actioning_readers,
                    $snapshot?->readers,
                    $snapshot?->previous_actioning_readers,
                    $snapshot?->previous_readers,
                    $freshness,
                ),
                'pageViews' => $this->metric(
                    $snapshot?->page_views,
                    $snapshot?->previous_page_views,
                    'Consent-matched article and public-page views.',
                    $freshness,
                ),
            ],
            'chart' => $this->chart($period),
            'snapshotReady' => $snapshot !== null,
            'snapshotFallback' => $isFallbackWindow,
            'snapshotWindow' => $snapshot === null ? null : [
                'from' => $snapshot->starts_on->toDateString(),
                'to' => $snapshot->ends_on->toDateString(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function metric(?int $value, ?int $previous, string $tooltip, FreshnessState $freshness): array
    {
        return [
            'value' => $value,
            'previousValue' => $previous,
            'comparison' => $this->comparison($value, $previous),
            'tooltip' => $tooltip,
            'source' => 'exact',
            'measured' => true,
            'freshness' => $freshness->value,
        ];
    }

    /** @return array<string, mixed> */
    private function rateMetric(
        ?int $numerator,
        ?int $denominator,
        ?int $previousNumerator,
        ?int $previousDenominator,
        FreshnessState $freshness,
    ): array {
        $value = $numerator === null || $denominator === null
            ? null
            : ($denominator === 0 ? 0.0 : round(($numerator / $denominator) * 100, 1));
        $previous = $previousNumerator === null || $previousDenominator === null
            ? null
            : ($previousDenominator === 0 ? 0.0 : round(($previousNumerator / $previousDenominator) * 100, 1));

        return [
            'value' => $value,
            'previousValue' => $previous,
            'comparison' => $this->comparison($value, $previous),
            'tooltip' => 'Measured readers who selected another article, shared, subscribed, or submitted a comment.',
            'source' => 'exact',
            'measured' => true,
            'unit' => 'percent',
            'freshness' => $freshness->value,
        ];
    }

    /** @return array{state: string, percent: float|null} */
    private function comparison(float|int|null $value, float|int|null $previous): array
    {
        if ($value === null || $previous === null) {
            return ['state' => 'unavailable', 'percent' => null];
        }

        if ($previous === 0 || $previous === 0.0) {
            return ['state' => $value > 0 ? 'increase' : 'steady', 'percent' => $value > 0 ? 100.0 : 0.0];
        }

        $percent = round((($value - $previous) / $previous) * 100, 1);

        return [
            'state' => $percent > 0 ? 'increase' : ($percent < 0 ? 'decrease' : 'steady'),
            'percent' => $percent,
        ];
    }

    /** @return array{resolution: string, points: list<array{date: string, readers: int, meaningfulReaders: int}>, summary: string} */
    private function chart(DashboardPeriod $period): array
    {
        if ($period->days() <= 90) {
            $metrics = AnalyticsDailySiteMetric::query()
                ->whereBetween('metric_date', [$period->startsOn, $period->endsOn])
                ->oldest('metric_date')
                ->get(['metric_date', 'readers', 'meaningful_readers']);
            $points = [];
            foreach ($metrics as $metric) {
                $points[] = [
                    'date' => $metric->metric_date->toDateString(),
                    'readers' => $metric->readers,
                    'meaningfulReaders' => $metric->meaningful_readers,
                ];
            }

            return [
                'resolution' => 'daily',
                'points' => $points,
                'summary' => $this->chartSummary($points, 'day'),
            ];
        }

        $metrics = AnalyticsWeeklySiteMetric::query()
            ->whereBetween('week_starts_on', [$period->startsOn->startOfWeek(), $period->endsOn])
            ->oldest('week_starts_on')
            ->get(['week_starts_on', 'readers', 'meaningful_readers']);
        $points = [];
        foreach ($metrics as $metric) {
            $points[] = [
                'date' => $metric->week_starts_on->toDateString(),
                'readers' => $metric->readers,
                'meaningfulReaders' => $metric->meaningful_readers,
            ];
        }

        return [
            'resolution' => 'weekly',
            'points' => $points,
            'summary' => $this->chartSummary($points, 'week'),
        ];
    }

    /** @param list<array{date: string, readers: int, meaningfulReaders: int}> $points */
    private function chartSummary(array $points, string $resolution): string
    {
        if ($points === []) {
            return 'No measured chart points are available for this period.';
        }

        $highest = $points[0];
        foreach ($points as $point) {
            if ($point['readers'] > $highest['readers']) {
                $highest = $point;
            }
        }

        return sprintf(
            '%d %s points. The highest point was %s with %d readers.',
            count($points),
            $resolution,
            $highest['date'],
            $highest['readers'],
        );
    }
}
