<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\ValueObjects\AnalyticsMetricColumns;
use App\Domain\Analytics\ValueObjects\AnalyticsMetricSet;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRow;

class BatchedAnalyticsReports
{
    /** @var array<string, string> */
    private const array EVENT_COLUMNS = [
        'article_progress_25' => 'article_progress_25',
        'article_progress_50' => 'article_progress_50',
        'article_progress_75' => 'article_progress_75',
        'article_progress_90' => 'article_progress_90',
        'article_engaged' => 'article_engaged',
        'select_content' => 'select_content',
        'share' => 'shares',
        'sign_up' => 'sign_ups',
        'comment_submit' => 'comment_submits',
        'click' => 'outbound_clicks',
        'file_download' => 'file_downloads',
    ];

    /** @var list<string> */
    private const array ACTION_EVENTS = ['select_content', 'share', 'sign_up', 'comment_submit'];

    public int $requestCount = 0;

    public int $pageCount = 0;

    public int $rowCount = 0;

    /** @var array<string, mixed>|null */
    public ?array $quota = null;

    public function __construct(private readonly AnalyticsReportingGateway $gateway) {}

    public function reset(): void
    {
        $this->requestCount = 0;
        $this->pageCount = 0;
        $this->rowCount = 0;
        $this->quota = null;
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $metrics
     * @param  array<string, string|list<string>>  $filters
     * @return list<AnalyticsReportRow>
     */
    public function raw(
        string $startsOn,
        string $endsOn,
        array $dimensions,
        array $metrics,
        array $filters = [],
    ): array {
        return $this->pages(new AnalyticsReportRequest(
            $startsOn,
            $endsOn,
            $dimensions,
            $metrics,
            $filters,
        ));
    }

    /**
     * Collect exact active-user metrics and additive event counts in batched
     * reports. Grouping dimensions are optional and never include identity.
     *
     * @param  list<string>  $groupingDimensions
     * @return array<string, AnalyticsMetricSet>
     */
    public function collect(string $startsOn, string $endsOn, array $groupingDimensions = []): array
    {
        $sets = [];

        foreach ($this->pages(new AnalyticsReportRequest(
            $startsOn,
            $endsOn,
            $groupingDimensions,
            ['activeUsers', 'sessions', 'screenPageViews'],
        )) as $row) {
            $key = $this->rowKey($row, $groupingDimensions);
            $sets[$key] = new AnalyticsMetricSet(
                $this->dimensions($row, $groupingDimensions),
                array_merge($this->emptyMetrics(), [
                    'readers' => $this->integerMetric($row, 'activeUsers'),
                    'sessions' => $this->integerMetric($row, 'sessions'),
                    'page_views' => $this->integerMetric($row, 'screenPageViews'),
                ]),
            );
        }

        $this->mergeActiveUsers(
            $sets,
            $startsOn,
            $endsOn,
            $groupingDimensions,
            ['eventName' => 'article_engaged'],
            'meaningful_readers',
        );
        $this->mergeActiveUsers(
            $sets,
            $startsOn,
            $endsOn,
            $groupingDimensions,
            ['eventName' => self::ACTION_EVENTS],
            'actioning_readers',
        );

        $eventDimensions = [...$groupingDimensions, 'eventName'];
        foreach ($this->pages(new AnalyticsReportRequest(
            $startsOn,
            $endsOn,
            $eventDimensions,
            ['eventCount'],
            ['eventName' => array_keys(self::EVENT_COLUMNS)],
        )) as $row) {
            $key = $this->rowKey($row, $groupingDimensions);
            $eventName = $row->dimensions['eventName'] ?? '';
            $column = self::EVENT_COLUMNS[$eventName] ?? null;

            if ($column === null) {
                continue;
            }

            $current = $sets[$key] ?? new AnalyticsMetricSet(
                $this->dimensions($row, $groupingDimensions),
                $this->emptyMetrics(),
            );
            $metrics = $current->metrics;
            $metrics[$column] = $this->integerMetric($row, 'eventCount');
            $sets[$key] = new AnalyticsMetricSet($current->dimensions, $metrics);
        }

        return $sets;
    }

    /**
     * @param  array<string, AnalyticsMetricSet>  $sets
     * @param  list<string>  $groupingDimensions
     * @param  array<string, string|list<string>>  $filters
     */
    private function mergeActiveUsers(
        array &$sets,
        string $startsOn,
        string $endsOn,
        array $groupingDimensions,
        array $filters,
        string $column,
    ): void {
        foreach ($this->pages(new AnalyticsReportRequest(
            $startsOn,
            $endsOn,
            $groupingDimensions,
            ['activeUsers'],
            $filters,
        )) as $row) {
            $key = $this->rowKey($row, $groupingDimensions);
            $current = $sets[$key] ?? new AnalyticsMetricSet(
                $this->dimensions($row, $groupingDimensions),
                $this->emptyMetrics(),
            );
            $metrics = $current->metrics;
            $metrics[$column] = $this->integerMetric($row, 'activeUsers');
            $sets[$key] = new AnalyticsMetricSet($current->dimensions, $metrics);
        }
    }

    /** @return list<AnalyticsReportRow> */
    private function pages(AnalyticsReportRequest $request): array
    {
        $rows = [];
        $offset = 0;

        do {
            $pageRequest = new AnalyticsReportRequest(
                $request->startsOn,
                $request->endsOn,
                $request->dimensions,
                $request->metrics,
                $request->dimensionFilters,
                $offset,
                $request->limit,
            );
            $page = $this->gateway->report($pageRequest);
            $this->requestCount++;
            $this->pageCount++;
            $this->rowCount += count($page->rows);
            $this->quota = $page->quota ?? $this->quota;
            array_push($rows, ...$page->rows);
            $hasNextPage = $page->hasNextPage($offset);
            $offset += count($page->rows);
        } while ($hasNextPage);

        return $rows;
    }

    /** @param list<string> $dimensions */
    private function rowKey(AnalyticsReportRow $row, array $dimensions): string
    {
        if ($dimensions === []) {
            return 'site';
        }

        return implode('|', array_map(
            static fn (string $dimension): string => $row->dimensions[$dimension] ?? '',
            $dimensions,
        ));
    }

    /**
     * @param  list<string>  $dimensions
     * @return array<string, string>
     */
    private function dimensions(AnalyticsReportRow $row, array $dimensions): array
    {
        $values = [];

        foreach ($dimensions as $dimension) {
            $values[$dimension] = $row->dimensions[$dimension] ?? '';
        }

        return $values;
    }

    private function integerMetric(AnalyticsReportRow $row, string $metric): int
    {
        return (int) round($row->metrics[$metric] ?? 0);
    }

    /** @return array<string, int> */
    private function emptyMetrics(): array
    {
        return AnalyticsMetricColumns::zeroed();
    }
}
