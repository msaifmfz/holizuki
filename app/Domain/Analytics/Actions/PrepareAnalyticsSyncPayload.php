<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsUrlAlias;
use App\Domain\Analytics\ValueObjects\AnalyticsMetricColumns;
use App\Domain\Analytics\ValueObjects\AnalyticsMetricSet;
use App\Domain\Analytics\ValueObjects\AnalyticsSyncPayload;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

class PrepareAnalyticsSyncPayload
{
    public function __construct(private readonly BatchedAnalyticsReports $reports) {}

    public function handle(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        bool $includeCustomPeriod = false,
    ): AnalyticsSyncPayload {
        $this->reports->reset();
        $aliases = AnalyticsUrlAlias::query()->pluck('post_id', 'content_key');
        $syncedAt = now();
        $start = $startsOn->toDateString();
        $end = $endsOn->toDateString();

        $dailySiteSets = $this->reports->collect($start, $end, ['date']);
        $dailyPostSets = $this->reports->collect($start, $end, ['date', 'customEvent:content_key']);
        $dailyChannelSets = $this->reports->collect($start, $end, ['date', 'sessionDefaultChannelGroup']);
        $weeklyStart = $startsOn->startOfWeek();
        $weeklyEnd = $endsOn->endOfWeek()->min(
            CarbonImmutable::today(config()->string('app.timezone')),
        );
        $weeklySiteSets = $this->reports->collect(
            $weeklyStart->toDateString(),
            $weeklyEnd->toDateString(),
            ['isoYearIsoWeek'],
        );
        $weeklyPostSets = $this->reports->collect(
            $weeklyStart->toDateString(),
            $weeklyEnd->toDateString(),
            ['isoYearIsoWeek', 'customEvent:content_key'],
        );

        $dailySite = [];
        $date = $startsOn;
        while ($date->lessThanOrEqualTo($endsOn)) {
            $dateValue = $date->format('Ymd');
            $dailySite[] = $this->row(
                ['metric_date' => $date->toDateString()],
                $dailySiteSets[$dateValue]->metrics ?? $this->emptyMetrics(),
                $syncedAt,
            );
            $date = $date->addDay();
        }

        $dailyPosts = [];
        foreach ($dailyPostSets as $set) {
            $contentKey = $set->dimensions['customEvent:content_key'] ?? '';
            $date = $this->gaDate($set->dimensions['date'] ?? '');
            if (! $this->validContentKey($contentKey)) {
                continue;
            }
            if ($date === null) {
                continue;
            }

            $dailyPosts[] = $this->row([
                'metric_date' => $date,
                'post_id' => $aliases->get($contentKey),
                'content_key' => $contentKey,
            ], $set->metrics, $syncedAt);
        }

        $dailyChannels = [];
        foreach ($dailyChannelSets as $set) {
            $date = $this->gaDate($set->dimensions['date'] ?? '');
            $channel = $set->dimensions['sessionDefaultChannelGroup'] ?? '';
            if ($date === null) {
                continue;
            }
            if ($channel === '') {
                continue;
            }
            if ($channel === '(not set)') {
                continue;
            }

            $dailyChannels[] = $this->row([
                'metric_date' => $date,
                'channel' => Str::limit($channel, 64, ''),
            ], $set->metrics, $syncedAt);
        }

        $weeklySite = [];
        foreach ($weeklySiteSets as $set) {
            $week = $this->isoWeek($set->dimensions['isoYearIsoWeek'] ?? '');
            if ($week === null) {
                continue;
            }

            $weeklySite[] = $this->row($week, $set->metrics, $syncedAt);
        }

        $weeklyPosts = [];
        foreach ($weeklyPostSets as $set) {
            $contentKey = $set->dimensions['customEvent:content_key'] ?? '';
            $week = $this->isoWeek($set->dimensions['isoYearIsoWeek'] ?? '');
            if (! $this->validContentKey($contentKey)) {
                continue;
            }
            if ($week === null) {
                continue;
            }

            $weeklyPosts[] = $this->row(array_merge($week, [
                'post_id' => $aliases->get($contentKey),
                'content_key' => $contentKey,
            ]), $set->metrics, $syncedAt);
        }

        $periods = $this->periods($startsOn, $endsOn, $includeCustomPeriod);

        return new AnalyticsSyncPayload(
            dailySite: $dailySite,
            dailyPosts: $dailyPosts,
            dailyChannels: $dailyChannels,
            weeklySite: $weeklySite,
            weeklyPosts: $weeklyPosts,
            snapshots: $this->snapshots($periods, $syncedAt),
            dimensionPeriods: $this->dimensionMetrics($periods, $syncedAt),
            unmappedPaths: $this->unmappedPaths($start, $end),
            requestCount: $this->reports->requestCount,
            pageCount: $this->reports->pageCount,
            rowCount: $this->reports->rowCount,
            quota: $this->reports->quota,
        );
    }

    /**
     * @param  array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>  $periods
     * @return list<array<string, mixed>>
     */
    private function snapshots(array $periods, DateTimeInterface $syncedAt): array
    {
        $snapshots = [];

        foreach ($periods as $periodKey => [$startsOn, $endsOn]) {
            [$comparisonStart, $comparisonEnd] = $this->comparisonWindow($startsOn, $endsOn);

            foreach ([
                ['site', [], 'site'],
                ['post', ['customEvent:content_key'], null],
                ['channel', ['sessionDefaultChannelGroup'], null],
            ] as [$scopeType, $dimensions, $singleScope]) {
                $current = $this->reports->collect($startsOn->toDateString(), $endsOn->toDateString(), $dimensions);
                $previous = $this->reports->collect($comparisonStart->toDateString(), $comparisonEnd->toDateString(), $dimensions);

                if ($singleScope === 'site' && ! isset($current['site'])) {
                    $current['site'] = new AnalyticsMetricSet([], $this->emptyMetrics());
                }

                foreach ($current as $key => $set) {
                    $scopeKey = $this->scopeKey($scopeType, $set, $singleScope);
                    if ($scopeKey === null) {
                        continue;
                    }

                    $previousMetrics = $previous[$key]->metrics ?? null;
                    $base = [
                        'scope_type' => $scopeType,
                        'scope_key' => $scopeKey,
                        'period_key' => $periodKey,
                        'starts_on' => $startsOn->toDateString(),
                        'ends_on' => $endsOn->toDateString(),
                        'comparison_starts_on' => $comparisonStart->toDateString(),
                        'comparison_ends_on' => $comparisonEnd->toDateString(),
                        'source' => 'exact',
                    ];
                    $metrics = $set->metrics;

                    $snapshots[] = $this->row(array_merge($base, [
                        'previous_readers' => $previousMetrics['readers'] ?? null,
                        'previous_meaningful_readers' => $previousMetrics['meaningful_readers'] ?? null,
                        'previous_actioning_readers' => $previousMetrics['actioning_readers'] ?? null,
                        'previous_page_views' => $previousMetrics['page_views'] ?? null,
                        'previous_select_content' => $previousMetrics['select_content'] ?? null,
                        'previous_shares' => $previousMetrics['shares'] ?? null,
                        'previous_sign_ups' => $previousMetrics['sign_ups'] ?? null,
                        'previous_comment_submits' => $previousMetrics['comment_submits'] ?? null,
                    ]), $metrics, $syncedAt);
                }
            }
        }

        return $snapshots;
    }

    /**
     * Audience breakdowns per dimension (country, device, source, landing page)
     * limited to the top-N values by readers, with the remainder folded into a
     * synthetic `(other)` row. Summing readers across values over-counts users
     * who span several values, which is acceptable for an "everything else" bar.
     *
     * @param  array<string, array{0: CarbonImmutable, 1: CarbonImmutable}>  $periods
     * @return list<array<string, mixed>>
     */
    private function dimensionMetrics(array $periods, DateTimeInterface $syncedAt): array
    {
        /** @var array<string, string> $dimensions */
        $dimensions = config()->array('analytics.audience_dimensions');
        $topN = config()->integer('analytics.audience_dimension_top_n');
        $rows = [];

        foreach ($periods as $periodKey => [$startsOn, $endsOn]) {
            [$comparisonStart, $comparisonEnd] = $this->comparisonWindow($startsOn, $endsOn);
            // A lifetime window has no preceding data by construction, so its
            // comparison stays null instead of issuing guaranteed-empty reports.
            $comparable = $periodKey !== 'lifetime';

            foreach ($dimensions as $dimensionType => $gaDimension) {
                $current = $this->dimensionReaders($startsOn, $endsOn, $gaDimension);
                $previous = $comparable
                    ? $this->dimensionReaders($comparisonStart, $comparisonEnd, $gaDimension)
                    : [];
                $hasPrevious = $previous !== [];
                uasort($current, static fn (array $a, array $b): int => $b['readers'] <=> $a['readers']);

                $top = array_slice($current, 0, $topN, true);
                $rest = array_slice($current, $topN, null, true);
                $base = [
                    'dimension_type' => $dimensionType,
                    'period_key' => $periodKey,
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $endsOn->toDateString(),
                ];
                $position = 0;

                foreach ($top as $key => $metrics) {
                    $rows[] = $this->row(array_merge($base, [
                        'dimension_value' => Str::limit($metrics['value'], 128, ''),
                        'position' => ++$position,
                        'previous_readers' => $hasPrevious ? ($previous[$key]['readers'] ?? 0) : null,
                        'previous_page_views' => $hasPrevious ? ($previous[$key]['page_views'] ?? 0) : null,
                    ]), ['readers' => $metrics['readers'], 'page_views' => $metrics['page_views']], $syncedAt);
                }

                if ($rest !== []) {
                    $previousRest = array_diff_key($previous, $top);
                    $rows[] = $this->row(array_merge($base, [
                        'dimension_value' => '(other)',
                        'position' => ++$position,
                        'previous_readers' => $hasPrevious ? array_sum(array_column($previousRest, 'readers')) : null,
                        'previous_page_views' => $hasPrevious ? array_sum(array_column($previousRest, 'page_views')) : null,
                    ]), [
                        'readers' => array_sum(array_column($rest, 'readers')),
                        'page_views' => array_sum(array_column($rest, 'page_views')),
                    ], $syncedAt);
                }
            }
        }

        return $rows;
    }

    /**
     * The equally long window immediately preceding the given one.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function comparisonWindow(CarbonImmutable $startsOn, CarbonImmutable $endsOn): array
    {
        $days = $startsOn->diffInDays($endsOn) + 1;
        $comparisonEnd = $startsOn->subDay();

        return [$comparisonEnd->subDays($days - 1), $comparisonEnd];
    }

    /** @return array<array-key, array{value: string, readers: int, page_views: int}> */
    private function dimensionReaders(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        string $gaDimension,
    ): array {
        $sets = [];

        foreach ($this->reports->raw(
            $startsOn->toDateString(),
            $endsOn->toDateString(),
            [$gaDimension],
            ['activeUsers', 'screenPageViews'],
        ) as $row) {
            $value = $row->dimensions[$gaDimension] ?? '';
            if ($value === '') {
                continue;
            }
            if ($value === '(not set)') {
                continue;
            }

            $sets[$value] = [
                'value' => $value,
                'readers' => (int) round($row->metrics['activeUsers'] ?? 0),
                'page_views' => (int) round($row->metrics['screenPageViews'] ?? 0),
            ];
        }

        return $sets;
    }

    /** @return array<string, array{0: CarbonImmutable, 1: CarbonImmutable}> */
    private function periods(
        CarbonImmutable $requestedStart,
        CarbonImmutable $requestedEnd,
        bool $includeCustomPeriod,
    ): array {
        $today = CarbonImmutable::today(config()->string('app.timezone'));
        $periods = [
            '7d' => [$today->subDays(6), $today],
            '28d' => [$today->subDays(27), $today],
            '90d' => [$today->subDays(89), $today],
            'year' => [$today->startOfYear(), $today],
        ];

        $matchesStandard = collect($periods)->contains(
            static fn (array $period): bool => $period[0]->isSameDay($requestedStart) && $period[1]->isSameDay($requestedEnd),
        );

        if ($includeCustomPeriod && ! $matchesStandard) {
            $periods['custom'] = [$requestedStart, $requestedEnd];
        }

        $earliestSnapshot = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->min('starts_on');
        $lifetimeStart = $requestedStart;
        if ($earliestSnapshot instanceof DateTimeInterface || is_string($earliestSnapshot)) {
            $storedStart = CarbonImmutable::parse($earliestSnapshot, config()->string('app.timezone'))->startOfDay();
            if ($storedStart->isBefore($lifetimeStart)) {
                $lifetimeStart = $storedStart;
            }
        }

        $matchesExistingPeriod = collect($periods)->contains(
            static fn (array $period): bool => $period[0]->isSameDay($lifetimeStart) && $period[1]->isSameDay($today),
        );
        if ($lifetimeStart->lessThanOrEqualTo($today) && ! $matchesExistingPeriod) {
            $periods['lifetime'] = [$lifetimeStart, $today];
        }

        return $periods;
    }

    private function scopeKey(string $scopeType, AnalyticsMetricSet $set, ?string $singleScope): ?string
    {
        if (is_string($singleScope)) {
            return $singleScope;
        }

        if ($scopeType === 'post') {
            $contentKey = $set->dimensions['customEvent:content_key'] ?? '';

            return $this->validContentKey($contentKey) ? $contentKey : null;
        }

        $channel = $set->dimensions['sessionDefaultChannelGroup'] ?? '';

        return $channel === '' || $channel === '(not set)'
            ? null
            : Str::limit('channel:'.$channel, 96, '');
    }

    /** @return list<array<string, mixed>> */
    private function unmappedPaths(string $startsOn, string $endsOn): array
    {
        $rows = $this->reports->raw(
            $startsOn,
            $endsOn,
            ['pagePath', 'customEvent:content_key'],
            ['activeUsers', 'screenPageViews'],
        );
        $aliasesByPath = AnalyticsUrlAlias::query()->pluck('content_key', 'path');
        $aliasesByContent = AnalyticsUrlAlias::query()->pluck('path', 'content_key');
        $unmapped = [];

        foreach ($rows as $row) {
            $rawPath = $row->dimensions['pagePath'] ?? '';
            $path = parse_url($rawPath, PHP_URL_PATH);
            $contentKey = $row->dimensions['customEvent:content_key'] ?? '';
            if (! is_string($path)) {
                continue;
            }
            if (! str_starts_with($path, '/posts/')) {
                continue;
            }

            $mappedByPath = $aliasesByPath->has($path);
            $mappedByContent = $this->validContentKey($contentKey) && $aliasesByContent->has($contentKey);
            if ($mappedByPath) {
                continue;
            }
            if ($mappedByContent) {
                continue;
            }

            $unmapped[$path] = [
                'path' => Str::limit($path, 2048, ''),
                'readers' => (int) round($row->metrics['activeUsers'] ?? 0),
                'page_views' => (int) round($row->metrics['screenPageViews'] ?? 0),
            ];
        }

        return array_values($unmapped);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $metrics
     * @return array<string, mixed>
     */
    private function row(array $attributes, array $metrics, DateTimeInterface $syncedAt): array
    {
        return array_merge($attributes, $metrics, [
            'synced_at' => $syncedAt,
            'created_at' => $syncedAt,
            'updated_at' => $syncedAt,
        ]);
    }

    private function gaDate(string $date): ?string
    {
        if (preg_match('/^\d{8}$/', $date) !== 1) {
            return null;
        }

        $parsedDate = CarbonImmutable::createFromFormat('!Ymd', $date, config()->string('app.timezone'));

        return $parsedDate?->toDateString();
    }

    /** @return array{iso_year: int, iso_week: int, week_starts_on: string}|null */
    private function isoWeek(string $value): ?array
    {
        if (preg_match('/^(\d{4})(\d{2})$/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];
        $week = (int) $matches[2];

        if ($week < 1 || $week > 53) {
            return null;
        }

        return [
            'iso_year' => $year,
            'iso_week' => $week,
            'week_starts_on' => CarbonImmutable::now(config()->string('app.timezone'))->setISODate($year, $week)->startOfDay()->toDateString(),
        ];
    }

    private function validContentKey(string $contentKey): bool
    {
        return preg_match('/^post:\d+$/', $contentKey) === 1;
    }

    /** @return array<string, int> */
    private function emptyMetrics(): array
    {
        return AnalyticsMetricColumns::zeroed();
    }
}
