<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Gateways;

use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use App\Domain\Analytics\ValueObjects\AnalyticsReportPage;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRow;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\Filter\InListFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter;
use Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\DimensionOrderBy;
use Google\Analytics\Data\V1beta\PropertyQuota;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportResponse;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use UnexpectedValueException;

class GoogleAnalyticsReportingGateway implements AnalyticsReportingGateway
{
    public function __construct(private readonly GoogleAnalyticsClientFactory $clients) {}

    public function report(AnalyticsReportRequest $request): AnalyticsReportPage
    {
        $response = $this->clients->reporting()->runReport(new RunReportRequest([
            'property' => $this->propertyName(),
            'date_ranges' => [new DateRange(['start_date' => $request->startsOn, 'end_date' => $request->endsOn])],
            'dimensions' => array_map(static fn (string $name): Dimension => new Dimension(['name' => $name]), $request->dimensions),
            'metrics' => array_map(static fn (string $name): Metric => new Metric(['name' => $name]), $request->metrics),
            'dimension_filter' => $this->dimensionFilter($request->dimensionFilters),
            'order_bys' => array_map(
                static fn (string $name): OrderBy => new OrderBy([
                    'dimension' => new DimensionOrderBy(['dimension_name' => $name]),
                ]),
                $request->dimensions,
            ),
            'offset' => $request->offset,
            'limit' => $request->limit,
            'return_property_quota' => true,
        ]), $this->callOptions());

        return $this->page($response);
    }

    public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
    {
        $response = $this->clients->reporting()->runRealtimeReport(new RunRealtimeReportRequest([
            'property' => $this->propertyName(),
            'dimensions' => array_map(static fn (string $name): Dimension => new Dimension(['name' => $name]), $request->dimensions),
            'metrics' => array_map(static fn (string $name): Metric => new Metric(['name' => $name]), $request->metrics),
            'dimension_filter' => $this->dimensionFilter($request->dimensionFilters),
            'limit' => $request->limit,
            'return_property_quota' => true,
        ]), $this->callOptions());

        return $this->page($response);
    }

    /** @param array<string, string|list<string>> $filters */
    private function dimensionFilter(array $filters): ?FilterExpression
    {
        if ($filters === []) {
            return null;
        }

        $expressions = [];

        foreach ($filters as $field => $value) {
            $filter = is_array($value)
                ? new Filter(['field_name' => $field, 'in_list_filter' => new InListFilter(['values' => $value])])
                : new Filter(['field_name' => $field, 'string_filter' => new StringFilter([
                    'match_type' => MatchType::EXACT,
                    'value' => $value,
                    'case_sensitive' => true,
                ])]);
            $expressions[] = new FilterExpression(['filter' => $filter]);
        }

        return count($expressions) === 1
            ? $expressions[0]
            : new FilterExpression(['and_group' => new FilterExpressionList(['expressions' => $expressions])]);
    }

    private function page(RunReportResponse|RunRealtimeReportResponse $response): AnalyticsReportPage
    {
        $dimensionNames = [];
        foreach ($response->getDimensionHeaders() as $header) {
            $dimensionNames[] = $header->getName();
        }

        $metricNames = [];
        foreach ($response->getMetricHeaders() as $header) {
            $metricNames[] = $header->getName();
        }

        $rows = [];
        foreach ($response->getRows() as $row) {
            $dimensions = [];
            $index = 0;
            foreach ($row->getDimensionValues() as $value) {
                $dimensionName = $dimensionNames[$index] ?? null;
                if ($dimensionName === null) {
                    throw new UnexpectedValueException('GA returned more dimension values than headers.');
                }

                $dimensions[$dimensionName] = $value->getValue();
                $index++;
            }
            $metrics = [];
            $index = 0;
            foreach ($row->getMetricValues() as $value) {
                $metricName = $metricNames[$index] ?? null;
                if ($metricName === null) {
                    throw new UnexpectedValueException('GA returned more metric values than headers.');
                }

                $metricValue = $value->getValue();
                $metrics[$metricName] = str_contains($metricValue, '.') ? (float) $metricValue : (int) $metricValue;
                $index++;
            }
            $rows[] = new AnalyticsReportRow($dimensions, $metrics);
        }

        $quota = $response->getPropertyQuota();
        $quotaData = null;
        if ($quota instanceof PropertyQuota) {
            $decodedQuota = json_decode($quota->serializeToJsonString(), true);
            if (is_array($decodedQuota)) {
                $quotaData = [];
                foreach ($decodedQuota as $key => $value) {
                    if (! is_string($key)) {
                        throw new UnexpectedValueException('GA returned invalid quota metadata.');
                    }

                    $quotaData[$key] = $value;
                }
            }
        }

        return new AnalyticsReportPage(
            rows: $rows,
            rowCount: $response->getRowCount(),
            quota: $quotaData,
        );
    }

    /** @return array{timeoutMillis: int} */
    private function callOptions(): array
    {
        return ['timeoutMillis' => config()->integer('analytics.request_timeout_seconds') * 1000];
    }

    private function propertyName(): string
    {
        $propertyId = config('analytics.property_id');

        if (! is_string($propertyId) || $propertyId === '') {
            throw new AnalyticsConfigurationException('Analytics property ID is not configured.');
        }

        return 'properties/'.$propertyId;
    }
}
