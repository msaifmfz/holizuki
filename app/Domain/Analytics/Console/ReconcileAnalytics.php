<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Console;

use App\Domain\Analytics\Actions\ProjectAnalyticsUrlAliases;
use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:reconcile')]
#[Description('Idempotently reconcile application-owned Google Analytics configuration and URL aliases')]
class ReconcileAnalytics extends Command
{
    public function handle(AnalyticsAdminGateway $admin, ProjectAnalyticsUrlAliases $aliases): int
    {
        $dimensions = config('analytics.custom_dimensions', []);
        if (! is_array($dimensions)) {
            $this->components->error('Analytics custom dimensions are invalid.');

            return self::FAILURE;
        }

        $result = $admin->reconcile(array_values(array_filter($dimensions, is_string(...))));
        $aliasCount = $aliases->reconcile();

        $this->components->info(sprintf(
            'Reconciled %d dimensions, key event: %s, retention: %s, and %d post aliases.',
            count($result->createdDimensions),
            $result->keyEventCreated ? 'created' : 'unchanged',
            $result->retentionUpdated ? 'updated' : 'unchanged',
            $aliasCount,
        ));

        return self::SUCCESS;
    }
}
