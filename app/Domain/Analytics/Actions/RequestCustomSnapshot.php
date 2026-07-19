<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Jobs\PrepareCustomSnapshot;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use App\Domain\Analytics\ValueObjects\DashboardPeriod;
use App\Domain\Identity\Models\User;

class RequestCustomSnapshot
{
    public function handle(User $user, DashboardPeriod $period): AnalyticsSnapshotPreparation
    {
        $key = hash('sha256', "site|{$period->startsOn->toDateString()}|{$period->endsOn->toDateString()}");
        $ready = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->whereDate('starts_on', $period->startsOn->toDateString())
            ->whereDate('ends_on', $period->endsOn->toDateString())
            ->exists();
        $preparation = AnalyticsSnapshotPreparation::query()->firstOrCreate(
            ['preparation_key' => $key],
            [
                'requested_by_id' => $user->id,
                'scope_key' => 'site',
                'starts_on' => $period->startsOn,
                'ends_on' => $period->endsOn,
                'status' => $ready ? 'ready' : 'queued',
                'completed_at' => $ready ? now() : null,
            ],
        );

        if ($ready && $preparation->status !== 'ready') {
            $preparation->update([
                'status' => 'ready',
                'sanitized_error' => null,
                'completed_at' => now(),
            ]);
        } elseif (
            ! $ready
            && ($preparation->wasRecentlyCreated || $preparation->status === 'failed')
        ) {
            $preparation->update(['status' => 'queued', 'sanitized_error' => null]);
            dispatch(new PrepareCustomSnapshot($preparation->id))->afterCommit();
        }

        return $preparation->refresh();
    }
}
