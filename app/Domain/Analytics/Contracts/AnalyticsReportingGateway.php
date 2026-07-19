<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\ValueObjects\AnalyticsReportPage;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;

interface AnalyticsReportingGateway
{
    public function report(AnalyticsReportRequest $request): AnalyticsReportPage;

    public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage;
}
