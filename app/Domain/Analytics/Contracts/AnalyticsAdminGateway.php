<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Contracts;

use App\Domain\Analytics\ValueObjects\AnalyticsPropertyState;
use App\Domain\Analytics\ValueObjects\AnalyticsReconcileResult;

interface AnalyticsAdminGateway
{
    public function inspect(): AnalyticsPropertyState;

    /** @param list<string> $dimensions */
    public function reconcile(array $dimensions): AnalyticsReconcileResult;
}
