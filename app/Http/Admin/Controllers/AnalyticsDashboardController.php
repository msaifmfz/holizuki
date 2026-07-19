<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Analytics\Actions\CalculateMomentum;
use App\Domain\Analytics\Actions\DashboardAnalyticsData;
use App\Domain\Analytics\Actions\RequestCustomSnapshot;
use App\Domain\Analytics\Actions\TrackAuthorProductEvent;
use App\Domain\Analytics\Models\AnalyticsDailyPostMetric;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Analytics\Models\AnalyticsMomentumSnapshot;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSetting;
use App\Domain\Analytics\Models\AnalyticsSnapshotPreparation;
use App\Domain\Analytics\Models\AnalyticsWeeklyPostMetric;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Analytics\Models\AuthorProductEvent;
use App\Domain\Analytics\ValueObjects\DashboardPeriod;
use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\Comment;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Requests\DashboardPeriodRequest;
use App\Http\Controller;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Post, audience, and realtime analytics are site-wide with no per-author
 * scoping (single-administrator assumption); only goals, achievements,
 * momentum, and recommendations are scoped to the requesting administrator.
 */
class AnalyticsDashboardController extends Controller
{
    public function index(
        DashboardPeriodRequest $request,
        DashboardAnalyticsData $data,
        CalculateMomentum $calculateMomentum,
        TrackAuthorProductEvent $trackEvent,
    ): Response {
        $user = $this->administrator($request->user());
        $period = $request->period();
        $trackEvent->handle($user, 'dashboard_open', $period->key);
        if ($request->has('period')) {
            $trackEvent->handle($user, 'period_change', $period->key);
        }

        return Inertia::render('dashboard', [
            ...$data->handle($period),
            'communityTotals' => $this->communityTotals(),
            'newMilestones' => $this->newMilestonesSinceLastOpen($user),
            'momentum' => Inertia::defer(fn (): array => $this->momentumData($calculateMomentum->handle($user))),
            'recommendations' => Inertia::defer(fn (): array => $this->recommendations($user)),
            'topPosts' => Inertia::defer(fn (): array => $this->postSnapshots($period, 5)->values()->all()),
        ]);
    }

    public function posts(DashboardPeriodRequest $request): Response
    {
        $this->ensureAnalyticsDashboardEnabled();
        $period = $request->period();

        return Inertia::render('dashboard/posts/index', [
            'period' => $this->periodData($period),
            'posts' => Inertia::defer(function () use ($period) {
                $paginator = $this->postSnapshotQuery($period)->paginate(25)->withQueryString();
                $posts = $this->postsForSnapshots($paginator->getCollection());

                return $paginator->through(fn (AnalyticsPeriodSnapshot $snapshot): array => $this->postSnapshotRow($snapshot, $posts));
            }),
        ]);
    }

    public function post(DashboardPeriodRequest $request, Post $post): Response
    {
        $this->ensureAnalyticsDashboardEnabled();
        $period = $request->period();
        $snapshot = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'post:'.$post->id)
            ->whereDate('starts_on', $period->startsOn->toDateString())
            ->whereDate('ends_on', $period->endsOn->toDateString())
            ->first();

        return Inertia::render('dashboard/posts/show', [
            'period' => $this->periodData($period),
            'post' => ['id' => $post->id, 'title' => $post->title, 'slug' => $post->slug],
            'metrics' => $snapshot === null ? null : $this->snapshotData($snapshot),
            'chart' => $this->postChart($post, $period),
        ]);
    }

    public function audience(DashboardPeriodRequest $request, DashboardAnalyticsData $data): Response
    {
        $this->ensureAnalyticsDashboardEnabled();
        $period = $request->period();
        $channels = AnalyticsPeriodSnapshot::query()
            ->where('scope_type', 'channel')
            ->whereDate('starts_on', $period->startsOn->toDateString())
            ->whereDate('ends_on', $period->endsOn->toDateString())
            ->orderByDesc('readers')
            ->get()
            ->map(fn (AnalyticsPeriodSnapshot $snapshot): array => [
                'channel' => str($snapshot->scope_key)->after('channel:')->toString(),
                ...$this->snapshotData($snapshot),
            ])->all();

        return Inertia::render('dashboard/audience', [
            ...$data->handle($period),
            'channels' => $channels,
        ]);
    }

    public function goals(DashboardPeriodRequest $request): Response
    {
        $user = $this->administrator($request->user());
        $goals = AuthorGoal::query()
            ->where('user_id', $user->id)
            ->with('periods')
            ->latest('effective_from')
            ->get()
            ->map(fn (AuthorGoal $goal): array => [
                'id' => $goal->id,
                'cadence' => $goal->cadence->value,
                'target' => $goal->target,
                'effectiveFrom' => $goal->effective_from->toDateString(),
                'effectiveUntil' => $goal->effective_until?->toDateString(),
                'periods' => $goal->periods->sortByDesc('starts_on')->take(24)->map(fn (AuthorGoalPeriod $period): array => [
                    'id' => $period->id,
                    'startsOn' => $period->starts_on->toDateString(),
                    'endsOn' => $period->ends_on->toDateString(),
                    'target' => $period->target,
                    'published' => $period->published_count,
                    'status' => $period->status->value,
                ])->all(),
            ])->all();

        return Inertia::render('dashboard/goals', ['goals' => $goals]);
    }

    public function achievements(DashboardPeriodRequest $request): Response
    {
        $user = $this->administrator($request->user());

        return Inertia::render('dashboard/achievements', [
            'milestones' => Inertia::defer(fn (): array => AnalyticsMilestone::query()
                ->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhereNull('user_id'))
                ->latest('achieved_at')
                ->get()
                ->map(fn (AnalyticsMilestone $milestone): array => [
                    'code' => $milestone->code,
                    'scopeKey' => $milestone->scope_key,
                    'evidence' => $milestone->evidence,
                    'achievedAt' => $milestone->achieved_at->toISOString(),
                ])->all()),
        ]);
    }

    public function requestSnapshot(
        DashboardPeriodRequest $request,
        RequestCustomSnapshot $requestSnapshot,
    ): JsonResponse {
        $this->ensureAnalyticsDashboardEnabled();
        $period = $request->period();
        abort_unless($period->key === 'custom', 422);
        $preparation = $requestSnapshot->handle($this->administrator($request->user()), $period);

        return response()->json($this->preparationData($preparation), $preparation->status === 'ready' ? 200 : 202);
    }

    public function snapshotStatus(Request $request, AnalyticsSnapshotPreparation $preparation): JsonResponse
    {
        $this->ensureAnalyticsDashboardEnabled();
        abort_unless($preparation->requested_by_id === $this->administrator($request->user())->id, 403);

        return response()->json($this->preparationData($preparation));
    }

    /** @return EloquentBuilder<AnalyticsPeriodSnapshot> */
    private function postSnapshotQuery(DashboardPeriod $period): EloquentBuilder
    {
        return AnalyticsPeriodSnapshot::query()
            ->where('scope_type', 'post')
            ->whereDate('starts_on', $period->startsOn->toDateString())
            ->whereDate('ends_on', $period->endsOn->toDateString())
            ->orderByDesc('readers');
    }

    /** @return Collection<int, non-empty-array<string, mixed>> */
    private function postSnapshots(DashboardPeriod $period, int $limit): Collection
    {
        $snapshots = $this->postSnapshotQuery($period)->limit($limit)->get();
        $posts = $this->postsForSnapshots($snapshots);

        return $snapshots
            ->map(fn (AnalyticsPeriodSnapshot $snapshot): array => $this->postSnapshotRow($snapshot, $posts))
            ->toBase();
    }

    /**
     * @param  Collection<int, AnalyticsPeriodSnapshot>  $snapshots
     * @return Collection<int|string, Post>
     */
    private function postsForSnapshots(Collection $snapshots): Collection
    {
        $postIds = $snapshots->map(fn (AnalyticsPeriodSnapshot $snapshot): int => (int) str($snapshot->scope_key)->after('post:')->toString());

        return Post::withTrashed()->whereKey($postIds)->get(['id', 'title', 'slug'])->keyBy('id')->toBase();
    }

    /**
     * @param  Collection<int|string, Post>  $posts
     * @return non-empty-array<string, mixed>
     */
    private function postSnapshotRow(AnalyticsPeriodSnapshot $snapshot, Collection $posts): array
    {
        $postId = (int) str($snapshot->scope_key)->after('post:')->toString();
        $post = $posts->get($postId);

        return [
            'post' => ['id' => $postId, 'title' => $post->title ?? 'Deleted post', 'slug' => $post->slug ?? null],
            ...$this->snapshotData($snapshot),
        ];
    }

    /** @return array<string, mixed> */
    private function snapshotData(AnalyticsPeriodSnapshot $snapshot): array
    {
        return [
            'readers' => $snapshot->readers,
            'meaningfulReaders' => $snapshot->meaningful_readers,
            'actioningReaders' => $snapshot->actioning_readers,
            'pageViews' => $snapshot->page_views,
            'shares' => $snapshot->shares,
            'signUps' => $snapshot->sign_ups,
            'comments' => $snapshot->comment_submits,
            'syncedAt' => $snapshot->synced_at->toISOString(),
            'source' => 'exact',
        ];
    }

    /** @return array<string, mixed> */
    private function momentumData(AnalyticsMomentumSnapshot $snapshot): array
    {
        return [
            'score' => $snapshot->score,
            'level' => $snapshot->level?->value,
            'components' => $snapshot->components,
            'freshness' => $snapshot->freshness->value,
            'calculatedAt' => $snapshot->calculated_at->toISOString(),
        ];
    }

    /**
     * Milestones achieved since the administrator's previous dashboard visit,
     * surfaced as a celebration callout. dashboard_open events are deduped
     * per session and day, so the second-latest one marks the prior visit.
     *
     * @return list<array{code: string, achievedAt: string}>
     */
    private function newMilestonesSinceLastOpen(User $user): array
    {
        $previousOpen = AuthorProductEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', 'dashboard_open')
            ->latest('occurred_at')
            ->latest('id')
            ->skip(1)
            ->value('occurred_at');

        if ($previousOpen === null) {
            return [];
        }

        return array_values(AnalyticsMilestone::query()
            ->where(fn (Builder $query) => $query->where('user_id', $user->id)->orWhereNull('user_id'))
            ->where('achieved_at', '>', $previousOpen)
            ->latest('achieved_at')
            ->limit(3)
            ->get()
            ->map(fn (AnalyticsMilestone $milestone): array => [
                'code' => $milestone->code,
                'achievedAt' => (string) $milestone->achieved_at->toISOString(),
            ])->all());
    }

    /** @return array{approvedComments: int, activeSubscribers: int, label: string} */
    private function communityTotals(): array
    {
        return [
            'approvedComments' => Comment::query()->where('status', CommentStatus::Approved)->count(),
            'activeSubscribers' => NewsletterSubscriber::query()->where('status', SubscriberStatus::Confirmed)->count(),
            'label' => 'Exact first-party totals — not GA measurements',
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recommendations(User $user): array
    {
        $exploratorySetting = AnalyticsSetting::query()
            ->where('key', 'show_exploratory_insights')
            ->first();
        $showExploratory = $exploratorySetting === null
            || ($exploratorySetting->value['value'] ?? true) === true;

        return array_values(AnalyticsInsight::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->unless($showExploratory, fn (Builder $query): Builder => $query->where('confidence', '!=', 'exploratory'))
            ->get()
            ->sortBy(fn (AnalyticsInsight $insight): string => sprintf(
                '%02d-%02d-%s-%06d',
                (int) ($insight->evidence['priority'] ?? 9),
                match ($insight->confidence->value) {
                    'high' => 0,
                    'medium' => 1,
                    default => 2,
                },
                $insight->rule_id,
                $insight->post_id ?? 0,
            ))
            ->take(5)
            ->map(fn (AnalyticsInsight $insight): array => [
                'id' => $insight->id,
                'ruleId' => $insight->rule_id,
                'confidence' => $insight->confidence->value,
                'observation' => $insight->observation,
                'suggestedAction' => $insight->suggested_action,
                'postId' => $insight->post_id,
            ])->all());
    }

    /** @return array{key: string, from: string, to: string, days: int} */
    private function periodData(DashboardPeriod $period): array
    {
        return ['key' => $period->key, 'from' => $period->startsOn->toDateString(), 'to' => $period->endsOn->toDateString(), 'days' => $period->days()];
    }

    /** @return array<string, mixed> */
    private function preparationData(AnalyticsSnapshotPreparation $preparation): array
    {
        return [
            'id' => $preparation->id,
            'key' => $preparation->preparation_key,
            'status' => $preparation->status,
            'from' => $preparation->starts_on->toDateString(),
            'to' => $preparation->ends_on->toDateString(),
            'error' => $preparation->sanitized_error,
        ];
    }

    /** @return array{resolution: string, points: list<array{date: string, readers: int, meaningfulReaders: int}>, summary: string} */
    private function postChart(Post $post, DashboardPeriod $period): array
    {
        $points = [];

        if ($period->days() <= 90) {
            $metrics = AnalyticsDailyPostMetric::query()
                ->where('content_key', 'post:'.$post->id)
                ->whereBetween('metric_date', [$period->startsOn, $period->endsOn])
                ->oldest('metric_date')
                ->get();
            foreach ($metrics as $metric) {
                $points[] = [
                    'date' => $metric->metric_date->toDateString(),
                    'readers' => $metric->readers,
                    'meaningfulReaders' => $metric->meaningful_readers,
                ];
            }

            return [
                'resolution' => 'daily',
                'points' => $points,
                'summary' => count($points).' daily points for this article.',
            ];
        }

        $metrics = AnalyticsWeeklyPostMetric::query()
            ->where('content_key', 'post:'.$post->id)
            ->whereBetween('week_starts_on', [$period->startsOn->startOfWeek(), $period->endsOn])
            ->oldest('week_starts_on')
            ->get();
        foreach ($metrics as $metric) {
            $points[] = [
                'date' => $metric->week_starts_on->toDateString(),
                'readers' => $metric->readers,
                'meaningfulReaders' => $metric->meaningful_readers,
            ];
        }

        return [
            'resolution' => 'weekly',
            'points' => $points,
            'summary' => count($points).' exact ISO-week points for this article.',
        ];
    }

    private function administrator(mixed $user): User
    {
        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        return $user;
    }

    private function ensureAnalyticsDashboardEnabled(): void
    {
        abort_unless(config()->boolean('analytics.dashboard_enabled'), 404);
    }
}
