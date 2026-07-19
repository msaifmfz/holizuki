<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use App\Domain\Analytics\Actions\SyncAnalyticsRange;
use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PrepareCustomSnapshot implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 80;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $preparationId) {}

    public function uniqueId(): string
    {
        return (string) $this->preparationId;
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping('analytics-property-sync')->shared()->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(SyncAnalyticsRange $sync): void
    {
        $preparation = AnalyticsSnapshotPreparation::query()->findOrFail($this->preparationId);
        $preparation->update(['status' => 'preparing', 'sanitized_error' => null]);

        try {
            $sync->handle(
                CarbonImmutable::instance($preparation->starts_on),
                CarbonImmutable::instance($preparation->ends_on),
                'custom-snapshot',
                $this->attempts(),
                includeCustomPeriod: true,
            );
            $preparation->update(['status' => 'ready', 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $preparation->update([
                'status' => 'failed',
                'sanitized_error' => 'The custom analytics range could not be prepared.',
            ]);

            throw $exception;
        }
    }
}
