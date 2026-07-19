<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\InsightStatus;
use App\Domain\Analytics\Models\AnalyticsInsight;
use InvalidArgumentException;

class DismissInsight
{
    public function handle(AnalyticsInsight $insight, string $reason): AnalyticsInsight
    {
        [$status, $days] = match ($reason) {
            'not_relevant', 'intentionally_designed' => [InsightStatus::Dismissed, 90],
            'insufficient_context', 'data_incorrect' => [InsightStatus::Dismissed, 28],
            'snooze' => [InsightStatus::Snoozed, 7],
            'already_completed' => [InsightStatus::Completed, null],
            default => throw new InvalidArgumentException('The insight action is not supported.'),
        };

        $insight->update([
            'status' => $status,
            'dismissal_reason' => $reason,
            'dismissed_until' => $days === null ? null : now()->addDays($days),
            'completed_at' => $status === InsightStatus::Completed ? now() : null,
        ]);

        return $insight->refresh();
    }
}
