<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\InsightConfidence;
use App\Domain\Analytics\Enums\InsightStatus;
use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSetting;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorGoal;
use App\Domain\Analytics\Models\AuthorGoalPeriod;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Single-administrator assumption: insights are unique on (rule_id, scope_key)
 * with no user dimension, and the end-of-run dismissal sweep is per-user —
 * multiple administrators would overwrite and dismiss each other's insights.
 *
 * @phpstan-type InsightEvidence array<string, bool|float|int|string|null>
 * @phpstan-type InsightCandidate array{
 *     rule_id: string,
 *     scope_key: string,
 *     post_id: int,
 *     confidence: InsightConfidence,
 *     priority: int,
 *     evidence_magnitude: float,
 *     evidence: InsightEvidence,
 *     observation: string,
 *     suggested_action: string
 * }
 */
class GenerateInsights
{
    /** @var list<string> */
    private const array STABLE_RULE_ORDER = [
        'publish_next',
        'expand_rising_topic',
        'refresh_older_article',
        'related_article',
        'improve_introduction',
        'improve_structure',
        'strengthen_cta',
        'improve_internal_links',
    ];

    /** @return Collection<int, AnalyticsInsight> */
    public function handle(User $user, ?CarbonImmutable $today = null): Collection
    {
        $today ??= CarbonImmutable::today(config()->string('app.timezone'));
        $startsOn = $today->subDays(27);
        $snapshots = AnalyticsPeriodSnapshot::query()
            ->where('scope_type', 'post')
            ->whereDate('starts_on', $startsOn->toDateString())
            ->whereDate('ends_on', $today->toDateString())
            ->get()
            ->keyBy('scope_key');
        $sevenDaySnapshots = AnalyticsPeriodSnapshot::query()
            ->where('scope_type', 'post')
            ->whereDate('starts_on', $today->subDays(6)->toDateString())
            ->whereDate('ends_on', $today->toDateString())
            ->get()
            ->keyBy('scope_key');
        $historyCounts = [];
        $historyRows = AnalyticsPeriodSnapshot::query()
            ->toBase()
            ->where('scope_type', 'post')
            ->where('period_key', '28d')
            ->whereDate('ends_on', '<=', $today->toDateString())
            ->groupBy('scope_key')
            ->selectRaw('scope_key, count(*) as aggregate')
            ->get();
        foreach ($historyRows as $historyRow) {
            if (is_string($historyRow->scope_key) && is_numeric($historyRow->aggregate)) {
                $historyCounts[$historyRow->scope_key] = (int) $historyRow->aggregate;
            }
        }
        $postIds = $snapshots->keys()->map(
            static fn (string $key): int => (int) str($key)->after('post:')->toString(),
        );
        $posts = Post::query()
            ->whereKey($postIds)
            ->published()
            ->with('tags:id,name,slug')
            ->get()
            ->keyBy('id');
        $evidence = $this->evidence($snapshots, $posts, $historyCounts);
        $medians = $this->medians($evidence);
        $freshness = $this->freshness();
        $isNewBlog = $this->isNewBlog($today);
        $hasFourteenDaysHistory = $this->dataDays($today) >= 14;
        $gap = $this->materialGap();
        $candidates = [];

        $publish = $this->publishNextCandidate($user, $today);
        if ($publish !== null) {
            $candidates[] = $publish;
        }

        foreach ($evidence as $postId => $values) {
            $post = $posts->get($postId);
            if (! $post instanceof Post) {
                continue;
            }

            $sevenDaySnapshot = $sevenDaySnapshots->get('post:'.$post->id);
            foreach ($this->postCandidates(
                $post,
                $values,
                $medians,
                $sevenDaySnapshot instanceof AnalyticsPeriodSnapshot ? $sevenDaySnapshot : null,
                $freshness,
                $isNewBlog,
                $hasFourteenDaysHistory,
                $gap,
                $today,
            ) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, function (array $left, array $right): int {
            $priority = $left['priority'] <=> $right['priority'];
            if ($priority !== 0) {
                return $priority;
            }

            $confidence = $this->confidenceRank($left['confidence']) <=> $this->confidenceRank($right['confidence']);
            if ($confidence !== 0) {
                return $confidence;
            }

            $rule = $this->ruleRank($left['rule_id']) <=> $this->ruleRank($right['rule_id']);
            $magnitude = $right['evidence_magnitude'] <=> $left['evidence_magnitude'];
            if ($magnitude !== 0) {
                return $magnitude;
            }

            if ($rule !== 0) {
                return $rule;
            }

            return $left['post_id'] <=> $right['post_id'];
        });
        $seen = [];
        $insights = [];
        $existingInsights = AnalyticsInsight::query()
            ->whereIn('rule_id', array_values(array_unique(array_column($candidates, 'rule_id'))))
            ->get()
            ->keyBy(static fn (AnalyticsInsight $insight): string => $insight->rule_id.'|'.$insight->scope_key);

        foreach ($candidates as $candidate) {
            $key = $candidate['rule_id'].'|'.$candidate['scope_key'];
            $seen[] = $key;
            $existing = $existingInsights->get($key);

            if ($existing !== null && $existing->status === InsightStatus::Completed) {
                continue;
            }

            if ($existing !== null && $existing->dismissed_until?->isFuture() === true) {
                continue;
            }

            $attributes = [
                'user_id' => $user->id,
                'post_id' => $candidate['post_id'],
                'confidence' => $candidate['confidence'],
                'status' => InsightStatus::Active,
                'evidence' => [
                    ...$candidate['evidence'],
                    'priority' => $candidate['priority'],
                    'evidence_magnitude' => $candidate['evidence_magnitude'],
                ],
                'observation' => $candidate['observation'],
                'suggested_action' => $candidate['suggested_action'],
                'dismissal_reason' => null,
                'detected_at' => $existing === null ? now() : $existing->detected_at,
                'last_seen_at' => now(),
                'dismissed_until' => null,
            ];

            if ($existing === null) {
                $insight = AnalyticsInsight::query()->create([
                    'rule_id' => $candidate['rule_id'],
                    'scope_key' => $candidate['scope_key'],
                    ...$attributes,
                ]);
            } else {
                $existing->update($attributes);
                $insight = $existing;
            }

            $insights[] = $insight;
        }

        AnalyticsInsight::query()
            ->where('user_id', $user->id)
            ->where('status', InsightStatus::Active)
            ->get()
            ->each(function (AnalyticsInsight $insight) use ($seen): void {
                if (! in_array($insight->rule_id.'|'.$insight->scope_key, $seen, true)) {
                    $insight->update([
                        'status' => InsightStatus::Dismissed,
                        'dismissal_reason' => 'conditions_changed',
                        'dismissed_until' => now()->addDays(28),
                    ]);
                }
            });

        return collect($insights);
    }

    /**
     * @param  EloquentCollection<int, AnalyticsPeriodSnapshot>  $snapshots
     * @param  EloquentCollection<int, Post>  $posts
     * @param  array<string, int>  $historyCounts
     * @return array<int, array<string, float|int>>
     */
    private function evidence(EloquentCollection $snapshots, EloquentCollection $posts, array $historyCounts): array
    {
        $evidence = [];

        foreach ($snapshots as $snapshot) {
            $postId = (int) str($snapshot->scope_key)->after('post:')->toString();
            $post = $posts->get($postId);
            if (! $post instanceof Post) {
                continue;
            }

            $readers = $snapshot->readers;
            $evidence[$postId] = [
                'readers' => $readers,
                'meaningful_readers' => $snapshot->meaningful_readers,
                'meaningful_rate' => $this->rate($snapshot->meaningful_readers, $readers),
                'progress_25_rate' => $this->rate($snapshot->article_progress_25, $readers),
                'progress_50_rate' => $this->rate($snapshot->article_progress_50, $readers),
                'progress_90_rate' => $this->rate($snapshot->article_progress_90, $readers),
                'continuation_rate' => $this->rate($snapshot->select_content, $readers),
                'action_rate' => $this->rate($snapshot->actioning_readers, $readers),
                'word_count' => $post->word_count,
                'previous_readers' => $snapshot->previous_readers ?? 0,
                'previous_meaningful_readers' => $snapshot->previous_meaningful_readers ?? 0,
                'complete_periods' => max(
                    $snapshot->previous_readers === null ? 1 : 2,
                    $historyCounts[$snapshot->scope_key] ?? 0,
                ),
            ];
        }

        return $evidence;
    }

    /**
     * @param  array<int, array<string, float|int>>  $evidence
     * @return array<string, float>
     */
    private function medians(array $evidence): array
    {
        $eligible = collect($evidence)->filter(static fn (array $row): bool => $row['readers'] >= 50);
        $median = static fn (string $key): float => (float) ($eligible->pluck($key)->median() ?? 0);

        return [
            'meaningful_rate' => $median('meaningful_rate'),
            'progress_25_rate' => $median('progress_25_rate'),
            'progress_50_rate' => $median('progress_50_rate'),
            'progress_90_rate' => $median('progress_90_rate'),
            'continuation_rate' => $median('continuation_rate'),
            'action_rate' => $median('action_rate'),
            'word_count' => $median('word_count'),
            'previous_meaningful_readers' => $median('previous_meaningful_readers'),
        ];
    }

    /**
     * @param  array<string, float|int>  $values
     * @param  array<string, float>  $medians
     * @return list<InsightCandidate>
     */
    private function postCandidates(
        Post $post,
        array $values,
        array $medians,
        ?AnalyticsPeriodSnapshot $sevenDaySnapshot,
        FreshnessState $freshness,
        bool $isNewBlog,
        bool $hasFourteenDaysHistory,
        int $gap,
        CarbonImmutable $today,
    ): array {
        $candidates = [];
        $readers = (int) $values['readers'];
        $ageDays = $post->published_at === null
            ? 0
            : CarbonImmutable::instance($post->published_at)->startOfDay()->diffInDays($today->startOfDay());
        $confidence = $this->confidence($values, $freshness, $gap);
        $negativeEligible = ! $isNewBlog && $readers >= 100 && $confidence instanceof InsightConfidence;

        if (
            $readers >= 100
            && $values['meaningful_rate'] >= $medians['meaningful_rate'] + $gap
            && $values['continuation_rate'] >= $medians['continuation_rate']
            && ! $this->hasTwoTagSibling($post)
        ) {
            $candidates[] = $this->candidate(
                'related_article', $post, $confidence ?? InsightConfidence::Exploratory, 3,
                ['readers' => $readers, 'meaningful_rate' => $values['meaningful_rate'], 'median_meaningful_rate' => $medians['meaningful_rate']],
                'Readers engage with this post more deeply than your eligible-post median.',
                'Publish a closely related continuation and link the two articles.',
            );
        }

        if ($negativeEligible && $ageDays >= 7 && $values['progress_25_rate'] <= $medians['progress_25_rate'] - $gap) {
            $candidates[] = $this->candidate(
                'improve_introduction', $post, $confidence, 3,
                ['readers' => $readers, 'progress_25_rate' => $values['progress_25_rate'], 'median_progress_25_rate' => $medians['progress_25_rate']],
                'Fewer readers reach 25% than the eligible-post median.',
                'Clarify the opening promise and shorten the path to the article’s main value.',
            );
        }

        if (
            $negativeEligible
            && $values['progress_50_rate'] >= $medians['progress_50_rate'] - 5
            && $values['progress_90_rate'] <= $medians['progress_90_rate'] - $gap
            && $values['word_count'] > $medians['word_count']
        ) {
            $candidates[] = $this->candidate(
                'improve_structure', $post, $confidence, 3,
                [
                    'readers' => $readers,
                    'progress_50_rate' => $values['progress_50_rate'],
                    'progress_90_rate' => $values['progress_90_rate'],
                    'median_progress_90_rate' => $medians['progress_90_rate'],
                    'word_count' => $values['word_count'],
                ],
                'Readers reach the middle, but ending progression trails comparable posts.',
                'Tighten the latter half with clearer sections, signposts, or a shorter conclusion.',
            );
        }

        if (
            $negativeEligible
            && $medians['action_rate'] > 0
            && $values['meaningful_rate'] >= $medians['meaningful_rate']
            && $values['progress_90_rate'] >= $medians['progress_90_rate']
            && $values['action_rate'] <= $medians['action_rate'] - $gap
        ) {
            $candidates[] = $this->candidate(
                'strengthen_cta', $post, $confidence, 3,
                ['readers' => $readers, 'action_rate' => $values['action_rate'], 'median_action_rate' => $medians['action_rate']],
                'Engaged readers finish this post, but fewer take a measured next action.',
                'Add one specific, relevant next step near the ending.',
            );
        }

        $previousMeaningful = (int) $values['previous_meaningful_readers'];
        $currentMeaningful = (int) $values['meaningful_readers'];
        $decline = $previousMeaningful === 0 ? 0 : (($previousMeaningful - $currentMeaningful) / $previousMeaningful) * 100;
        if (
            $negativeEligible
            && $ageDays >= 180
            && $previousMeaningful >= max(25, (int) round($medians['previous_meaningful_readers']))
            && $currentMeaningful >= 10
            && $decline >= 30
        ) {
            $candidates[] = $this->candidate(
                'refresh_older_article', $post, $confidence, 2,
                ['current_meaningful_readers' => $currentMeaningful, 'previous_meaningful_readers' => $previousMeaningful, 'decline_percent' => round($decline, 1), 'age_days' => (int) $ageDays],
                'Meaningful readership for this older post declined by at least 30%.',
                'Review dated examples, links, and framing, then publish a reader-visible refresh.',
            );
        }

        if ($readers >= 50 && $currentMeaningful >= 25 && $hasFourteenDaysHistory && $sevenDaySnapshot instanceof AnalyticsPeriodSnapshot && $sevenDaySnapshot->previous_meaningful_readers !== null) {
            $growth = $sevenDaySnapshot->previous_meaningful_readers === 0
                ? ($sevenDaySnapshot->meaningful_readers > 0 ? 100 : 0)
                : (($sevenDaySnapshot->meaningful_readers - $sevenDaySnapshot->previous_meaningful_readers) / $sevenDaySnapshot->previous_meaningful_readers) * 100;
            if ($growth >= 50) {
                $candidates[] = $this->candidate(
                    'expand_rising_topic', $post, $confidence ?? InsightConfidence::Exploratory, 1,
                    ['readers' => $readers, 'meaningful_readers' => $currentMeaningful, 'seven_day_growth_percent' => round($growth, 1)],
                    'Meaningful readership for this topic rose at least 50% over the preceding week.',
                    'Build on the momentum with a focused follow-up article.',
                );
            }
        }

        if ($negativeEligible && $values['continuation_rate'] <= $medians['continuation_rate'] - $gap && $this->hasRelatedCandidate($post)) {
            $candidates[] = $this->candidate(
                'improve_internal_links', $post, $confidence, 3,
                ['readers' => $readers, 'continuation_rate' => $values['continuation_rate'], 'median_continuation_rate' => $medians['continuation_rate']],
                'Fewer readers continue to another article than the eligible-post median.',
                'Add a contextual link to the strongest related published article.',
            );
        }

        return $candidates;
    }

    /** @return InsightCandidate|null */
    private function publishNextCandidate(User $user, CarbonImmutable $today): ?array
    {
        $goal = AuthorGoal::query()
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
            ->latest('effective_from')
            ->first();
        if ($goal === null) {
            return null;
        }

        $period = AuthorGoalPeriod::query()
            ->where('goal_id', $goal->id)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today)
            ->first();
        $draft = Post::query()->where('status', PostStatus::Draft)->latest('updated_at')->first();

        if ($period === null || $draft === null || $period->published_count >= $period->target) {
            return null;
        }

        $remaining = $period->target - $period->published_count;
        $daysRemaining = (int) $today->diffInDays($period->ends_on) + 1;
        $atRisk = $remaining >= $daysRemaining;

        return $this->candidate(
            'publish_next', $draft, InsightConfidence::High, $atRisk ? 0 : 4,
            ['published' => $period->published_count, 'target' => $period->target, 'days_remaining' => $daysRemaining],
            $atRisk ? 'Your active publishing goal is at risk.' : 'Your active publishing goal is still incomplete.',
            'Continue the most recently updated draft and publish it before this goal period ends.',
        );
    }

    /** @param array<string, float|int> $values */
    private function confidence(array $values, FreshnessState $freshness, int $gap): ?InsightConfidence
    {
        if ($values['readers'] >= 250 && $values['complete_periods'] >= 3 && $freshness === FreshnessState::Fresh) {
            return InsightConfidence::High;
        }

        // Medium confidence requires the admin-tuned comparison gap to stay
        // at or above a separate floor — loosening the gap below the floor
        // deliberately downgrades borderline findings to Exploratory.
        $mediumGapFloor = config()->integer('analytics.medium_confidence_min_gap_points');
        if ($values['readers'] >= 100 && $values['complete_periods'] >= 2 && in_array($freshness, [FreshnessState::Fresh, FreshnessState::Delayed], true) && $gap >= $mediumGapFloor) {
            return InsightConfidence::Medium;
        }

        return $values['readers'] >= 50 ? InsightConfidence::Exploratory : null;
    }

    private function freshness(): FreshnessState
    {
        $completedAt = AnalyticsSyncRun::query()->where('status', 'succeeded')->latest('completed_at')->value('completed_at');
        $date = $completedAt instanceof CarbonInterface ? $completedAt : (is_string($completedAt) ? CarbonImmutable::parse($completedAt) : null);

        return FreshnessState::forLastSuccess($date);
    }

    private function isNewBlog(CarbonImmutable $today): bool
    {
        $site = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->where('period_key', '28d')
            ->latest('ends_on')
            ->first();
        $readers = $site->readers ?? 0;

        return $this->dataDays($today) < 14 && $readers < 100;
    }

    private function dataDays(CarbonImmutable $today): int
    {
        $firstRun = AnalyticsSyncRun::query()
            ->where('status', 'succeeded')
            ->whereNotNull('starts_on')
            ->oldest('starts_on')
            ->first();
        $firstDate = $firstRun?->starts_on;

        return $firstDate === null ? 0 : (int) $firstDate->diffInDays($today) + 1;
    }

    private function materialGap(): int
    {
        $setting = AnalyticsSetting::query()->where('key', 'material_gap_points')->first();
        $value = $setting?->value['value'] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return config()->integer('analytics.material_gap_points');
    }

    private function hasTwoTagSibling(Post $post): bool
    {
        $tagIds = $post->tags->modelKeys();
        if (count($tagIds) < 2) {
            return false;
        }

        return Post::query()
            ->published()
            ->whereKeyNot($post->id)
            ->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tagIds), '>=', 2)
            ->exists();
    }

    private function hasRelatedCandidate(Post $post): bool
    {
        $tagIds = $post->tags->modelKeys();

        return Post::query()
            ->published()
            ->whereKeyNot($post->id)
            ->where(function (Builder $query) use ($post, $tagIds): void {
                if ($post->category_id !== null) {
                    $query->where('category_id', $post->category_id);
                }
                if ($tagIds !== []) {
                    $query->orWhereHas('tags', fn (Builder $tagQuery): Builder => $tagQuery->whereKey($tagIds));
                }
            })
            ->exists();
    }

    /**
     * @param  InsightEvidence  $evidence
     * @return InsightCandidate
     */
    private function candidate(
        string $ruleId,
        Post $post,
        InsightConfidence $confidence,
        int $priority,
        array $evidence,
        string $observation,
        string $suggestedAction,
    ): array {
        return [
            'rule_id' => $ruleId,
            'scope_key' => 'post:'.$post->id,
            'post_id' => $post->id,
            'confidence' => $confidence,
            'priority' => $priority,
            'evidence_magnitude' => $this->evidenceMagnitude($ruleId, $evidence),
            'evidence' => $evidence,
            'observation' => $observation,
            'suggested_action' => $suggestedAction,
        ];
    }

    private function confidenceRank(InsightConfidence $confidence): int
    {
        return match ($confidence) {
            InsightConfidence::High => 0,
            InsightConfidence::Medium => 1,
            InsightConfidence::Exploratory => 2,
        };
    }

    private function ruleRank(string $ruleId): int
    {
        foreach (self::STABLE_RULE_ORDER as $rank => $stableRuleId) {
            if ($ruleId === $stableRuleId) {
                return $rank;
            }
        }

        return count(self::STABLE_RULE_ORDER);
    }

    /** @param InsightEvidence $evidence */
    private function evidenceMagnitude(string $ruleId, array $evidence): float
    {
        return match ($ruleId) {
            'related_article' => $this->difference($evidence, 'meaningful_rate', 'median_meaningful_rate'),
            'improve_introduction' => $this->difference($evidence, 'median_progress_25_rate', 'progress_25_rate'),
            'strengthen_cta' => $this->difference($evidence, 'median_action_rate', 'action_rate'),
            'improve_internal_links' => $this->difference($evidence, 'median_continuation_rate', 'continuation_rate'),
            'refresh_older_article' => $this->numericEvidence($evidence, 'decline_percent'),
            'expand_rising_topic' => $this->numericEvidence($evidence, 'seven_day_growth_percent'),
            'improve_structure' => $this->difference($evidence, 'median_progress_90_rate', 'progress_90_rate'),
            'publish_next' => $this->numericEvidence($evidence, 'target') - $this->numericEvidence($evidence, 'published'),
            default => 0.0,
        };
    }

    /** @param InsightEvidence $evidence */
    private function difference(array $evidence, string $higherKey, string $lowerKey): float
    {
        return max(0.0, $this->numericEvidence($evidence, $higherKey) - $this->numericEvidence($evidence, $lowerKey));
    }

    /** @param InsightEvidence $evidence */
    private function numericEvidence(array $evidence, string $key): float
    {
        $value = $evidence[$key] ?? 0;

        return is_float($value) || is_int($value) ? (float) $value : 0.0;
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round(($numerator / $denominator) * 100, 1);
    }
}
