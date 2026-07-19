<?php

declare(strict_types=1);

use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\Jobs\PrepareCustomSnapshot;
use App\Domain\Analytics\Models\AnalyticsDailySiteMetric;
use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AnalyticsWeeklyPostMetric;
use App\Domain\Analytics\Models\AuthorProductEvent;
use App\Domain\Analytics\ValueObjects\AnalyticsReportPage;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRow;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config()->set([
        'app.timezone' => 'UTC',
        'analytics.dashboard_enabled' => true,
    ]);
    CarbonImmutable::setTestNow('2026-07-19 12:00:00');
    Cache::flush();
});

it('falls back to the latest completed snapshot until the next sync closes the window', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create(['completed_at' => now()->subHours(5)]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'period_key' => '28d',
        'starts_on' => '2026-06-21',
        'ends_on' => '2026-07-18',
        'readers' => 42,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('metrics.readers.value', 42)
            ->where('snapshotReady', true)
            ->where('snapshotFallback', true)
            ->where('snapshotWindow.to', '2026-07-18')
            ->where('freshness.state', 'delayed'));
});

it('celebrates milestones achieved since the previous dashboard visit', function (): void {
    $user = User::factory()->create();
    AuthorProductEvent::factory()->create([
        'user_id' => $user->id,
        'event_id' => 'dashboard_open',
        'occurred_at' => now()->subDay(),
    ]);
    AnalyticsMilestone::factory()->create([
        'user_id' => $user->id,
        'code' => 'published_5_posts',
        'achieved_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('newMilestones.0.code', 'published_5_posts'));
});

it('rejects snapshot status polling for another administrator preparation', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $preparation = AnalyticsSnapshotPreparation::factory()->create(['requested_by_id' => $owner->id]);

    $this->actingAs($other)
        ->getJson(route('dashboard.analytics.snapshots.show', $preparation))
        ->assertForbidden();
    $this->actingAs($owner)
        ->getJson(route('dashboard.analytics.snapshots.show', $preparation))
        ->assertOk();
});

it('distinguishes exact zero metrics from unavailable metrics', function (): void {
    $user = User::factory()->create();
    AnalyticsSyncRun::factory()->create(['completed_at' => now()]);
    AnalyticsPeriodSnapshot::factory()->create([
        'scope_type' => 'site',
        'scope_key' => 'site',
        'period_key' => '28d',
        'starts_on' => '2026-06-22',
        'ends_on' => '2026-07-19',
        'readers' => 0,
        'meaningful_readers' => 0,
        'actioning_readers' => 0,
        'page_views' => 0,
        'previous_readers' => 0,
        'previous_meaningful_readers' => 0,
        'previous_actioning_readers' => 0,
        'previous_page_views' => 0,
    ]);
    AnalyticsDailySiteMetric::factory()->create([
        'metric_date' => '2026-07-19',
        'readers' => 0,
        'meaningful_readers' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('metrics.readers.value', 0)
            ->where('metrics.readers.comparison.state', 'steady')
            ->where('metrics.meaningfulReaders.value', 0)
            ->where('snapshotReady', true)
            ->where('chart.points.0.readers', 0));

    AnalyticsPeriodSnapshot::query()->delete();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('metrics.readers.value', null)
            ->where('metrics.readers.comparison.state', 'unavailable')
            ->where('snapshotReady', false));
});

it('uses exact ISO-week chart points for article periods over ninety days', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create();
    AnalyticsWeeklyPostMetric::factory()->create([
        'post_id' => $post,
        'content_key' => 'post:'.$post->id,
        'iso_year' => 2026,
        'iso_week' => 29,
        'week_starts_on' => '2026-07-13',
        'readers' => 42,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.posts.show', [
            'post' => $post,
            'period' => 'year',
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('chart.resolution', 'weekly')
            ->where('chart.points.0.readers', 42)
            ->where('chart.points.0.date', '2026-07-13'));
});

it('deduplicates custom snapshot preparation and validates inclusive range limits', function (): void {
    Bus::fake();
    $user = User::factory()->create();
    $payload = [
        'period' => 'custom',
        'from' => '2026-06-01',
        'to' => '2026-06-30',
    ];

    $first = $this->actingAs($user)->postJson(route('dashboard.analytics.snapshots.store'), $payload);
    $second = $this->actingAs($user)->postJson(route('dashboard.analytics.snapshots.store'), $payload);

    $first->assertStatus(202)->assertJson(['status' => 'queued']);
    $second->assertStatus(202)->assertJson(['id' => $first->json('id')]);
    expect(AnalyticsSnapshotPreparation::query()->count())->toBe(1);
    Bus::assertDispatchedTimes(PrepareCustomSnapshot::class, 1);

    $this->actingAs($user)->postJson(route('dashboard.analytics.snapshots.store'), [
        'period' => 'custom',
        'from' => '2025-07-18',
        'to' => '2026-07-19',
    ])->assertUnprocessable()->assertJsonValidationErrors('period');
});

it('returns exact cached realtime readers and a marked stale fallback', function (): void {
    $user = User::factory()->create();
    $post = Post::factory()->published()->create(['title' => 'Realtime post']);
    $gateway = new class($post->id) implements AnalyticsReportingGateway
    {
        public int $calls = 0;

        public bool $fail = false;

        public function __construct(private readonly int $postId) {}

        public function report(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            throw new RuntimeException('Not used.');
        }

        public function realtime(AnalyticsReportRequest $request): AnalyticsReportPage
        {
            if ($this->fail) {
                throw new RuntimeException('Temporary realtime failure.');
            }

            $this->calls++;

            return $request->dimensions === []
                ? new AnalyticsReportPage([
                    new AnalyticsReportRow([], ['activeUsers' => 7]),
                ], 1)
                : new AnalyticsReportPage([
                    new AnalyticsReportRow(
                        ['customEvent:content_key' => 'post:'.$this->postId],
                        ['activeUsers' => 5],
                    ),
                    new AnalyticsReportRow(
                        ['customEvent:content_key' => 'post:999999'],
                        ['activeUsers' => 4],
                    ),
                ], 2);
        }
    };
    app()->instance(AnalyticsReportingGateway::class, $gateway);

    $this->actingAs($user)
        ->getJson(route('dashboard.analytics.realtime'))
        ->assertOk()
        ->assertJson([
            'available' => true,
            'stale' => false,
            'readers' => 7,
            'activePosts' => [[
                'contentKey' => 'post:'.$post->id,
                'title' => 'Realtime post',
                'readers' => 5,
            ]],
        ]);
    expect($gateway->calls)->toBe(2);

    $this->actingAs($user)->getJson(route('dashboard.analytics.realtime'))->assertOk();
    expect($gateway->calls)->toBe(2);

    CarbonImmutable::setTestNow('2026-07-19 12:01:01');
    $gateway->fail = true;
    $this->actingAs($user)
        ->getJson(route('dashboard.analytics.realtime'))
        ->assertOk()
        ->assertJson(['available' => true, 'stale' => true, 'readers' => 7]);
});

it('renders every author analytics and community workspace for an administrator', function (): void {
    $user = User::factory()->create();

    foreach ([
        route('dashboard'),
        route('dashboard.posts.index'),
        route('dashboard.audience'),
        route('dashboard.goals'),
        route('dashboard.achievements'),
        route('dashboard.analytics.settings.edit'),
        route('community.comments.index'),
        route('community.subscribers.index'),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertOk();
    }
});

it('deduplicates dashboard opens per session and day across period changes', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('dashboard', ['period' => '7d']))->assertOk();

    expect(AuthorProductEvent::query()->where('event_id', 'dashboard_open')->count())->toBe(1)
        ->and(AuthorProductEvent::query()->where('event_id', 'period_change')->count())->toBe(1);
});

it('keeps community progress available while disabled analytics surfaces stay closed', function (): void {
    config()->set('analytics.dashboard_enabled', false);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('enabled', false));

    foreach ([
        route('dashboard.posts.index'),
        route('dashboard.audience'),
        route('dashboard.analytics.realtime'),
    ] as $url) {
        $this->actingAs($user)->get($url)->assertNotFound();
    }

    $this->actingAs($user)->get(route('community.comments.index'))->assertOk();
});
