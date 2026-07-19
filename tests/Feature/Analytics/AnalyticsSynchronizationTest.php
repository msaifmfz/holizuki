<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\BatchedAnalyticsReports;
use App\Domain\Analytics\Actions\CheckAnalyticsHealth;
use App\Domain\Analytics\Actions\NotifyAnalyticsSyncFailure;
use App\Domain\Analytics\Actions\PrepareAnalyticsSyncPayload;
use App\Domain\Analytics\Actions\SyncAnalyticsRange;
use App\Domain\Analytics\Contracts\AnalyticsAdminGateway;
use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\Enums\SyncStatus;
use App\Domain\Analytics\Exceptions\AnalyticsConfigurationException;
use App\Domain\Analytics\Gateways\GoogleAnalyticsClientFactory;
use App\Domain\Analytics\Jobs\SyncAnalyticsMonth;
use App\Domain\Analytics\Mail\AnalyticsSyncFailureMail;
use App\Domain\Analytics\Models\AnalyticsDailySiteMetric;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AnalyticsUnmappedPath;
use App\Domain\Analytics\Models\AnalyticsWeeklyPostMetric;
use App\Domain\Analytics\Models\AnalyticsWeeklySiteMetric;
use App\Domain\Analytics\ValueObjects\AnalyticsPropertyState;
use App\Domain\Analytics\ValueObjects\AnalyticsReconcileResult;
use App\Domain\Analytics\ValueObjects\AnalyticsReportPage;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRow;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Cache::flush();
    config()->set([
        'app.timezone' => 'UTC',
        'analytics.measurement_id' => 'G-TEST123',
        'analytics.property_id' => '123456',
        'analytics.stream_id' => '987654',
        'analytics.service_account_base64' => base64_encode('{}'),
    ]);

    app()->bind(AnalyticsAdminGateway::class, fn (): AnalyticsAdminGateway => healthyAnalyticsAdminGateway());
});

it('notifies verified administrators after three failures at most once per day', function (): void {
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    Mail::fake();
    $administrator = User::factory()->create();
    User::factory()->unverified()->create();
    User::factory()->reader()->create();
    $notify = resolve(NotifyAnalyticsSyncFailure::class);

    AnalyticsSyncRun::factory()->count(2)->create(['status' => SyncStatus::Failed]);
    $notify->handle('A safe error');
    Mail::assertNothingQueued();

    AnalyticsSyncRun::factory()->create(['status' => SyncStatus::Failed]);
    $notify->handle('A safe error');
    $notify->handle('A safe error');

    Mail::assertQueued(AnalyticsSyncFailureMail::class, 1);
    Mail::assertQueued(
        AnalyticsSyncFailureMail::class,
        fn (AnalyticsSyncFailureMail $mail): bool => $mail->hasTo($administrator->email)
            && $mail->sanitizedError === 'A safe error',
    );

    CarbonImmutable::setTestNow('2026-07-20 12:00:01');
    $notify->handle('A safe error');
    Mail::assertQueued(AnalyticsSyncFailureMail::class, 2);
});

it('stores exact range readers instead of summing daily active users and is idempotent', function (): void {
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    app()->bind(AnalyticsReportingGateway::class, fn (): AnalyticsReportingGateway => exactReadersGateway());

    $sync = resolve(SyncAnalyticsRange::class);
    $sync->handle(CarbonImmutable::parse('2026-07-18'), CarbonImmutable::parse('2026-07-19'), 'test', includeCustomPeriod: true);
    $sync->handle(CarbonImmutable::parse('2026-07-18'), CarbonImmutable::parse('2026-07-19'), 'test', includeCustomPeriod: true);

    expect((int) AnalyticsDailySiteMetric::query()->sum('readers'))->toBe(20)
        ->and(AnalyticsDailySiteMetric::query()->count())->toBe(2)
        ->and(AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->where('period_key', 'custom')
            ->value('readers'))->toBe(15)
        ->and(AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->where('period_key', 'custom')
            ->value('source'))->toBe('exact')
        ->and(AnalyticsSyncRun::query()->where('status', 'succeeded')->count())->toBe(2);

    $requestCounts = AnalyticsSyncRun::query()->oldest('id')->pluck('request_count');
    expect($requestCounts[0])->toBeGreaterThan(0)
        ->and($requestCounts[1])->toBe($requestCounts[0]);
});

it('does not create custom period snapshots for scheduled syncs', function (): void {
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    app()->bind(AnalyticsReportingGateway::class, fn (): AnalyticsReportingGateway => exactReadersGateway());

    resolve(SyncAnalyticsRange::class)->handle(
        CarbonImmutable::parse('2026-07-16'),
        CarbonImmutable::parse('2026-07-19'),
        'analytics:sync-recent',
    );

    expect(AnalyticsPeriodSnapshot::query()->where('period_key', 'custom')->exists())->toBeFalse()
        ->and(AnalyticsPeriodSnapshot::query()->where('period_key', '7d')->exists())->toBeTrue();
});

it('paginates batched reports and retains quota metadata from the latest page', function (): void {
    $gateway = new class implements AnalyticsReportingGateway
    {
        /** @var list<int> */
        public array $offsets = [];

        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            $this->offsets[] = $request->offset;

            return new AnalyticsReportPage(
                [new AnalyticsReportRow(['date' => $request->offset === 0 ? '20260718' : '20260719'], ['activeUsers' => 1])],
                2,
                ['page' => $request->offset + 1],
            );
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('Not used.');
        }
    };
    $reports = new BatchedAnalyticsReports($gateway);

    $rows = $reports->raw('2026-07-18', '2026-07-19', ['date'], ['activeUsers']);

    expect($rows)->toHaveCount(2)
        ->and($gateway->offsets)->toBe([0, 1])
        ->and($reports->requestCount)->toBe(2)
        ->and($reports->pageCount)->toBe(2)
        ->and($reports->rowCount)->toBe(2)
        ->and($reports->quota)->toBe(['page' => 2]);
});

it('blocks synchronization when identifiers timezone dimensions key event or retention disagree', function (): void {
    $gateway = new class implements AnalyticsAdminGateway
    {
        public function inspect(): AnalyticsPropertyState
        {
            return new AnalyticsPropertyState(
                propertyId: 'wrong-property',
                streamId: 'wrong-stream',
                measurementId: 'G-WRONG',
                timezone: 'America/New_York',
                customDimensions: [],
                keyEvents: [],
                retentionMonths: 2,
                googleSignalsDisabled: false,
                enhancedMeasurementStreamEnabled: true,
                enabledEnhancedMeasurements: ['scrolls', 'outbound clicks'],
            );
        }

        public function reconcile(array $dimensions): AnalyticsReconcileResult
        {
            throw new RuntimeException('Not used.');
        }
    };
    app()->instance(AnalyticsAdminGateway::class, $gateway);

    $health = resolve(CheckAnalyticsHealth::class)->handle();

    expect($health->healthy)->toBeFalse()
        ->and($health->errors)->toContain('The configured GA property does not match the inspected property.')
        ->toContain('The configured GA web stream does not match the inspected stream.')
        ->toContain('The configured GA measurement ID does not match the web stream.')
        ->toContain('The GA property timezone must match the application timezone.')
        ->toContain('The sign_up event is not configured as a GA key event.')
        ->toContain('GA event retention must be set to 14 months.')
        ->toContain('Google Signals must be disabled for this property.')
        ->toContain('Automatic enhanced measurement must be disabled. Enabled: scrolls, outbound clicks.');

    expect(fn () => resolve(SyncAnalyticsRange::class)->handle(
        CarbonImmutable::parse('2026-07-18'),
        CarbonImmutable::parse('2026-07-19'),
        'test',
    ))->toThrow(AnalyticsConfigurationException::class);
    expect(AnalyticsDailySiteMetric::query()->exists())->toBeFalse();
});

it('rejects malformed service-account secrets before creating a Google client', function (): void {
    config()->set('analytics.service_account_base64', 'not base64!');

    expect(fn () => resolve(GoogleAnalyticsClientFactory::class)->reporting())
        ->toThrow(AnalyticsConfigurationException::class, 'not valid base64');
});

it('persists exact ISO weeks and records only genuinely unmapped post paths', function (): void {
    CarbonImmutable::setTestNow('2026-12-31 12:00:00');
    $post = Post::factory()->published()->create(['slug' => 'mapped-post']);
    app()->bind(
        AnalyticsReportingGateway::class,
        fn (): AnalyticsReportingGateway => weeklyAndUnmappedGateway($post->id, $post->slug),
    );

    resolve(SyncAnalyticsRange::class)->handle(
        CarbonImmutable::parse('2026-12-28'),
        CarbonImmutable::parse('2026-12-31'),
        'test',
    );

    $siteWeek = AnalyticsWeeklySiteMetric::query()->sole();
    $postWeek = AnalyticsWeeklyPostMetric::query()->sole();
    $unmapped = AnalyticsUnmappedPath::query()->sole();

    expect($siteWeek->iso_year)->toBe(2026)
        ->and($siteWeek->iso_week)->toBe(53)
        ->and($siteWeek->week_starts_on->toDateString())->toBe('2026-12-28')
        ->and($postWeek->post_id)->toBe($post->id)
        ->and($postWeek->content_key)->toBe('post:'.$post->id)
        ->and($unmapped->path)->toBe('/posts/missing-post')
        ->and($unmapped->readers)->toBe(9)
        ->and($unmapped->page_views)->toBe(12);
});

it('requests complete ISO weeks when a sync range crosses a month boundary', function (): void {
    CarbonImmutable::setTestNow('2026-08-15 12:00:00');
    $gateway = new class implements AnalyticsReportingGateway
    {
        /** @var list<array{startsOn: string, endsOn: string}> */
        public array $weeklyRanges = [];

        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            if (in_array('isoYearIsoWeek', $request->dimensions, true)) {
                $this->weeklyRanges[] = [
                    'startsOn' => $request->startsOn,
                    'endsOn' => $request->endsOn,
                ];
            }

            return new AnalyticsReportPage([], 0);
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('Not used.');
        }
    };
    app()->instance(AnalyticsReportingGateway::class, $gateway);

    resolve(PrepareAnalyticsSyncPayload::class)->handle(
        CarbonImmutable::parse('2026-07-31'),
        CarbonImmutable::parse('2026-08-01'),
    );

    expect($gateway->weeklyRanges)->not->toBeEmpty()
        ->and(array_values(array_unique(array_map(
            static fn (array $range): string => $range['startsOn'].'|'.$range['endsOn'],
            $gateway->weeklyRanges,
        ))))->toBe(['2026-07-27|2026-08-02']);
});

it('never overwrites existing values when a report fails', function (): void {
    AnalyticsDailySiteMetric::factory()->create([
        'metric_date' => '2026-07-19',
        'readers' => 91,
    ]);
    app()->bind(AnalyticsReportingGateway::class, fn (): AnalyticsReportingGateway => new class implements AnalyticsReportingGateway
    {
        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('credential text that must not be persisted');
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('not used');
        }
    });

    expect(fn () => resolve(SyncAnalyticsRange::class)->handle(
        CarbonImmutable::parse('2026-07-19'),
        CarbonImmutable::parse('2026-07-19'),
        'test',
    ))->toThrow(RuntimeException::class);

    expect(AnalyticsDailySiteMetric::query()->value('readers'))->toBe(91)
        ->and(AnalyticsSyncRun::query()->where('status', 'failed')->value('sanitized_error'))
        ->toBe('Analytics synchronization failed. Review the application logs for details.');
});

it('stores an exact lifetime snapshot when the imported history predates the year', function (): void {
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    app()->bind(AnalyticsReportingGateway::class, fn (): AnalyticsReportingGateway => exactReadersGateway());

    resolve(SyncAnalyticsRange::class)->handle(
        CarbonImmutable::parse('2025-07-18'),
        CarbonImmutable::parse('2025-07-18'),
        'test',
    );

    $lifetime = AnalyticsPeriodSnapshot::query()
        ->where('scope_key', 'site')
        ->where('period_key', 'lifetime')
        ->sole();

    expect($lifetime->starts_on->toDateString())->toBe('2025-07-18')
        ->and($lifetime->ends_on->toDateString())->toBe('2026-07-19')
        ->and($lifetime->readers)->toBe(15)
        ->and($lifetime->source)->toBe('exact');
});

it('queues a serialized calendar-month backfill chain with retry-safe jobs', function (): void {
    Bus::fake();

    $this->artisan('analytics:backfill', [
        '--from' => '2026-01-15',
        '--to' => '2026-03-02',
    ])->assertSuccessful();

    Bus::assertChained([
        new SyncAnalyticsMonth('2026-01-15', '2026-01-31'),
        new SyncAnalyticsMonth('2026-02-01', '2026-02-28'),
        new SyncAnalyticsMonth('2026-03-01', '2026-03-02'),
    ]);

    $job = new SyncAnalyticsMonth('2026-01-01', '2026-01-31');
    $backoff = $job->backoff();

    expect($job->tries)->toBe(5)
        ->and($job->timeout)->toBeLessThan(90)
        ->and($backoff)->toHaveCount(4)
        ->and($backoff[0])->toBeBetween(60, 75)
        ->and($backoff[1])->toBeBetween(120, 150)
        ->and($backoff[2])->toBeBetween(240, 300)
        ->and($backoff[3])->toBeBetween(480, 600);
});

function healthyAnalyticsAdminGateway(): AnalyticsAdminGateway
{
    return new class implements AnalyticsAdminGateway
    {
        public function inspect(): AnalyticsPropertyState
        {
            return new AnalyticsPropertyState(
                propertyId: '123456',
                streamId: '987654',
                measurementId: 'G-TEST123',
                timezone: 'UTC',
                customDimensions: config()->array('analytics.custom_dimensions'),
                keyEvents: ['sign_up'],
                retentionMonths: 14,
            );
        }

        public function reconcile(array $dimensions): AnalyticsReconcileResult
        {
            return new AnalyticsReconcileResult([], false, false);
        }
    };
}

function exactReadersGateway(): AnalyticsReportingGateway
{
    return new class implements AnalyticsReportingGateway
    {
        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            $rows = $this->rows($request);

            return new AnalyticsReportPage($rows, count($rows), ['tokensPerDay' => ['remaining' => 1000]]);
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            return new AnalyticsReportPage([], 0);
        }

        /** @return list<AnalyticsReportRow> */
        private function rows(AnalyticsReportRequest $request): array
        {
            if ($request->dimensions === ['date']) {
                if (isset($request->dimensionFilters['eventName'])) {
                    return [];
                }

                return [
                    new AnalyticsReportRow(['date' => '20260718'], ['activeUsers' => 10, 'sessions' => 12, 'screenPageViews' => 14]),
                    new AnalyticsReportRow(['date' => '20260719'], ['activeUsers' => 10, 'sessions' => 11, 'screenPageViews' => 13]),
                ];
            }

            if ($request->dimensions === []) {
                $eventFilter = $request->dimensionFilters['eventName'] ?? null;

                return match ($eventFilter) {
                    'article_engaged' => [new AnalyticsReportRow([], ['activeUsers' => 5])],
                    ['select_content', 'share', 'sign_up', 'comment_submit'] => [new AnalyticsReportRow([], ['activeUsers' => 3])],
                    default => [new AnalyticsReportRow([], ['activeUsers' => 15, 'sessions' => 20, 'screenPageViews' => 27])],
                };
            }

            return [];
        }
    };
}

function weeklyAndUnmappedGateway(int $postId, string $slug): AnalyticsReportingGateway
{
    return new readonly class($postId, $slug) implements AnalyticsReportingGateway
    {
        public function __construct(
            private int $postId,
            private string $slug,
        ) {}

        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            $rows = match ($request->dimensions) {
                ['isoYearIsoWeek'] => $request->dimensionFilters === []
                    ? [new AnalyticsReportRow(
                        ['isoYearIsoWeek' => '202653'],
                        ['activeUsers' => 11, 'sessions' => 13, 'screenPageViews' => 17],
                    )]
                    : [],
                ['isoYearIsoWeek', 'customEvent:content_key'] => $request->dimensionFilters === []
                    ? [new AnalyticsReportRow(
                        ['isoYearIsoWeek' => '202653', 'customEvent:content_key' => 'post:'.$this->postId],
                        ['activeUsers' => 7, 'sessions' => 8, 'screenPageViews' => 10],
                    )]
                    : [],
                ['pagePath', 'customEvent:content_key'] => [
                    new AnalyticsReportRow(
                        ['pagePath' => '/posts/'.$this->slug.'?utm_source=ignored', 'customEvent:content_key' => 'post:'.$this->postId],
                        ['activeUsers' => 5, 'screenPageViews' => 6],
                    ),
                    new AnalyticsReportRow(
                        ['pagePath' => '/posts/missing-post?email=removed', 'customEvent:content_key' => '(not set)'],
                        ['activeUsers' => 9, 'screenPageViews' => 12],
                    ),
                    new AnalyticsReportRow(
                        ['pagePath' => '/about', 'customEvent:content_key' => '(not set)'],
                        ['activeUsers' => 20, 'screenPageViews' => 22],
                    ),
                ],
                default => [],
            };

            return new AnalyticsReportPage($rows, count($rows));
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('Not used.');
        }
    };
}
