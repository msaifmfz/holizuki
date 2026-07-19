<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

final readonly class AnalyticsReportPage
{
    /**
     * @param  list<AnalyticsReportRow>  $rows
     * @param  array<string, mixed>|null  $quota
     */
    public function __construct(
        public array $rows,
        public int $rowCount,
        public ?array $quota = null,
    ) {}

    public function hasNextPage(int $offset): bool
    {
        return $this->rows !== [] && $offset + count($this->rows) < $this->rowCount;
    }
}
