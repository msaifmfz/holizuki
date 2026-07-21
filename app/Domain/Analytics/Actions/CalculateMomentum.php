<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\MomentumLevel;
use App\Domain\Analytics\Models\AnalyticsMomentumSnapshot;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AnalyticsSyncRun;
use App\Domain\Analytics\Models\AuthorActivityEvent;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

class CalculateMomentum
{
    /**
     * Relative component weights; the score renormalizes over the weights of
     * whichever components are eligible for the day. Reader trends dominate
     * so momentum reflects audience feedback rather than self-set targets.
     *
     * @var array<string, float>
     */
    private const array COMPONENT_WEIGHTS = [
        'consistency' => 25.0,
        'meaningful_reader_trend' => 30.0,
        'reader_action_rate_trend' => 25.0,
        'content_maintenance' => 20.0,
    ];

    public function handle(User $user, ?CarbonImmutable $today = null): AnalyticsMomentumSnapshot
    {
        $today ??= CarbonImmutable::today(config()->string('app.timezone'));
        $lastSuccess = AnalyticsSyncRun::query()
            ->where('status', 'succeeded')
            ->latest('completed_at')
            ->value('completed_at');
        $lastSuccessAt = $lastSuccess instanceof CarbonInterface
            ? $lastSuccess
            : (is_string($lastSuccess) ? CarbonImmutable::parse($lastSuccess) : null);
        $freshness = FreshnessState::forLastSuccess($lastSuccessAt);
        $previous = AnalyticsMomentumSnapshot::query()
            ->where('user_id', $user->id)
            ->latest('scored_on')
            ->first();

        if (in_array($freshness, [FreshnessState::Stale, FreshnessState::Unavailable], true) && $previous !== null) {
            return AnalyticsMomentumSnapshot::query()->updateOrCreate(
                ['user_id' => $user->id, 'scored_on' => $today->startOfDay()],
                [
                    'score' => $previous->score,
                    'level' => $previous->level,
                    'components' => $previous->components,
                    'freshness' => $freshness,
                    'data_freshness_at' => $lastSuccessAt ?? $previous->data_freshness_at,
                    'calculated_at' => $previous->calculated_at,
                ],
            );
        }

        $components = [];
        $eligible = [];

        $consistency = $this->consistency($user, $today);
        if ($consistency === null) {
            $components['consistency'] = ['weight' => self::COMPONENT_WEIGHTS['consistency'], 'status' => 'gathering_data'];
        } else {
            $eligible['consistency'] = ['weight' => self::COMPONENT_WEIGHTS['consistency'], 'value' => $consistency, 'status' => 'ready'];
        }

        $site = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->where('period_key', '7d')
            ->whereDate('ends_on', $today->toDateString())
            ->first();
        $isNew = $this->isNewBlog($site, $lastSuccessAt, $today);

        if ($isNew) {
            $components['meaningful_reader_trend'] = ['weight' => self::COMPONENT_WEIGHTS['meaningful_reader_trend'], 'status' => 'hidden_for_new_blog'];
            $components['reader_action_rate_trend'] = ['weight' => self::COMPONENT_WEIGHTS['reader_action_rate_trend'], 'status' => 'hidden_for_new_blog'];
        } elseif ($site === null || $site->previous_meaningful_readers === null || $site->previous_readers === null || $site->previous_actioning_readers === null) {
            $components['meaningful_reader_trend'] = ['weight' => self::COMPONENT_WEIGHTS['meaningful_reader_trend'], 'status' => 'gathering_data'];
            $components['reader_action_rate_trend'] = ['weight' => self::COMPONENT_WEIGHTS['reader_action_rate_trend'], 'status' => 'gathering_data'];
        } else {
            $eligible['meaningful_reader_trend'] = [
                'weight' => self::COMPONENT_WEIGHTS['meaningful_reader_trend'],
                'value' => $this->trendFactor($site->meaningful_readers, $site->previous_meaningful_readers),
                'status' => 'ready',
            ];
            $currentActionRate = $site->readers === 0 ? 0.0 : $site->actioning_readers / $site->readers;
            $previousActionRate = $site->previous_readers === 0 ? 0.0 : $site->previous_actioning_readers / $site->previous_readers;
            $eligible['reader_action_rate_trend'] = [
                'weight' => self::COMPONENT_WEIGHTS['reader_action_rate_trend'],
                'value' => $this->trendFactor($currentActionRate, $previousActionRate),
                'status' => 'ready',
            ];
        }

        $maintenance = AuthorActivityEvent::query()
            ->where('user_id', $user->id)
            ->where('event_id', 'post_maintained')
            ->whereBetween('occurred_at', [$today->startOfWeek(), $today->endOfWeek()])
            ->distinct('post_id')
            ->count('post_id');
        $eligible['content_maintenance'] = [
            'weight' => self::COMPONENT_WEIGHTS['content_maintenance'],
            'value' => min($maintenance, 1),
            'status' => 'ready',
        ];

        $eligibleWeight = array_sum(array_column($eligible, 'weight'));
        $score = 0.0;
        foreach ($eligible as $key => $component) {
            $normalizedWeight = ($component['weight'] / $eligibleWeight) * 100;
            $componentScore = $component['value'] * $normalizedWeight;
            $components[$key] = [
                ...$component,
                'normalized_weight' => round($normalizedWeight, 2),
                'score' => round($componentScore, 2),
            ];
            $score += $componentScore;
        }

        $roundedScore = max(0, min(100, (int) round($score)));

        return $this->store(
            $user,
            $today,
            $roundedScore,
            MomentumLevel::forScore($roundedScore),
            $components,
            $freshness,
            $lastSuccessAt,
        );
    }

    public function trendFactor(float|int $current, float|int $previous): float
    {
        $currentValue = (float) $current;
        $previousValue = (float) $previous;

        if ($previousValue === 0.0) {
            return $currentValue > 0.0 ? 1.0 : 0.5;
        }

        if ($currentValue === 0.0) {
            return 0.0;
        }

        $change = ($currentValue - $previousValue) / $previousValue;

        return max(0.0, min(1.0, ($change + 0.25) / 0.5));
    }

    /**
     * Fraction of the last eight completed ISO weeks containing at least one
     * first publication. Gathers data until the author has published and two
     * full weeks have passed since their first publication.
     */
    private function consistency(User $user, CarbonImmutable $today): ?float
    {
        $firstPublishedAt = AuthorPublication::query()
            ->where('author_id', $user->id)
            ->min('first_published_at');
        if (! is_string($firstPublishedAt) && ! $firstPublishedAt instanceof DateTimeInterface) {
            return null;
        }

        $firstPublished = $firstPublishedAt instanceof DateTimeInterface
            ? CarbonImmutable::instance($firstPublishedAt)
            : CarbonImmutable::parse($firstPublishedAt);
        if ($firstPublished->greaterThan($today->subWeeks(2))) {
            return null;
        }

        $windowStart = $today->subWeeks(8)->startOfWeek();
        $windowEnd = $today->startOfWeek()->subSecond();
        $publishedWeeks = AuthorPublication::query()
            ->where('author_id', $user->id)
            ->whereBetween('first_published_at', [$windowStart, $windowEnd])
            ->get(['first_published_at'])
            ->map(static fn (AuthorPublication $publication): string => CarbonImmutable::instance($publication->first_published_at)->format('o-W'))
            ->unique()
            ->count();

        return min($publishedWeeks / 8, 1.0);
    }

    private function isNewBlog(
        ?AnalyticsPeriodSnapshot $site,
        ?CarbonInterface $lastSuccess,
        CarbonImmutable $today,
    ): bool {
        if (! $site instanceof AnalyticsPeriodSnapshot || ! $lastSuccess instanceof CarbonInterface) {
            return true;
        }

        $firstDataDate = AnalyticsSyncRun::query()
            ->where('status', 'succeeded')
            ->whereNotNull('starts_on')
            ->min('starts_on');
        $dataDays = is_string($firstDataDate)
            ? CarbonImmutable::parse($firstDataDate)->diffInDays($today) + 1
            : 0;

        return $dataDays < 14 && $site->readers < 100;
    }

    /**
     * @param  array<string, mixed>  $components
     */
    private function store(
        User $user,
        CarbonImmutable $today,
        ?int $score,
        ?MomentumLevel $level,
        array $components,
        FreshnessState $freshness,
        ?CarbonInterface $lastSuccess,
    ): AnalyticsMomentumSnapshot {
        return AnalyticsMomentumSnapshot::query()->updateOrCreate(
            ['user_id' => $user->id, 'scored_on' => $today->startOfDay()],
            [
                'score' => $score,
                'level' => $level,
                'components' => $components,
                'freshness' => $freshness,
                'data_freshness_at' => $lastSuccess,
                'calculated_at' => now(),
            ],
        );
    }
}
