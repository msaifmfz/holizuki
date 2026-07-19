<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\SyncStatus;
use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

class SyncAnalyticsRange
{
    public function __construct(
        private readonly CheckAnalyticsHealth $health,
        private readonly ProjectAnalyticsUrlAliases $aliases,
        private readonly PrepareAnalyticsSyncPayload $prepare,
        private readonly PersistAnalyticsSyncPayload $persist,
        private readonly RefreshAuthorMotivation $motivation,
        private readonly NotifyAnalyticsSyncFailure $notifyFailure,
    ) {}

    /**
     * The lock must outlive a worst-case full sync (health check, alias
     * reconciliation, and every paginated GA report); an expired lock would
     * let a concurrent scheduled sync interleave its delete/upsert batches.
     */
    private const int LOCK_SECONDS = 900;

    public function handle(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        string $command,
        int $attempt = 1,
        bool $includeCustomPeriod = false,
    ): AnalyticsSyncRun {
        $this->validateRange($startsOn, $endsOn);

        $run = Cache::lock('analytics:property-import', self::LOCK_SECONDS)->block(
            5,
            fn (): AnalyticsSyncRun => $this->synchronize($startsOn, $endsOn, $command, $attempt, $includeCustomPeriod),
        );

        if (! $run instanceof AnalyticsSyncRun) {
            throw new LogicException('Analytics synchronization did not return a sync run.');
        }

        return $run;
    }

    private function synchronize(
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        string $command,
        int $attempt,
        bool $includeCustomPeriod,
    ): AnalyticsSyncRun {

        $run = AnalyticsSyncRun::query()->create([
            'run_id' => (string) Str::uuid(),
            'command' => Str::limit($command, 48, ''),
            'status' => SyncStatus::Running,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'attempt' => $attempt,
            'started_at' => now(),
        ]);

        try {
            $health = $this->health->handle();
            if (! $health->healthy) {
                throw new AnalyticsConfigurationException(implode(' ', $health->errors));
            }

            $this->aliases->reconcile();
            $payload = $this->prepare->handle($startsOn, $endsOn, $includeCustomPeriod);
            DB::transaction(function () use ($payload, $startsOn, $endsOn, $run): void {
                $this->persist->handle($payload, $startsOn, $endsOn);
                $run->update([
                    'status' => SyncStatus::Succeeded,
                    'request_count' => $payload->requestCount,
                    'page_count' => $payload->pageCount,
                    'row_count' => $payload->rowCount,
                    'quota' => $payload->quota,
                    'completed_at' => now(),
                ]);
            });
            $this->motivation->handle();
            Cache::add(
                'analytics:dashboard-cache-version',
                config()->integer('analytics.snapshot_cache_version'),
            );
            Cache::increment('analytics:dashboard-cache-version');

            return $run->refresh();
        } catch (Throwable $exception) {
            $sanitizedError = $this->sanitizedError($exception);
            $run->update([
                'status' => SyncStatus::Failed,
                'sanitized_error' => $sanitizedError,
                'completed_at' => now(),
            ]);
            $this->notifyFailure->handle($sanitizedError);

            throw $exception;
        }
    }

    private function validateRange(CarbonImmutable $startsOn, CarbonImmutable $endsOn): void
    {
        if ($startsOn->isAfter($endsOn)) {
            throw new InvalidArgumentException('The analytics start date must be on or before the end date.');
        }

        if ($startsOn->diffInDays($endsOn) + 1 > 366) {
            throw new InvalidArgumentException('Analytics ranges may contain at most 366 inclusive days.');
        }
    }

    private function sanitizedError(Throwable $exception): string
    {
        return $exception instanceof AnalyticsConfigurationException
            ? Str::limit($exception->getMessage(), 1000, '')
            : 'Analytics synchronization failed. Review the application logs for details.';
    }
}
