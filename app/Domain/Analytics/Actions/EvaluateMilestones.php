<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\AnalyticsMilestone;
use App\Domain\Analytics\Models\AnalyticsPeriodSnapshot;
use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Single-administrator assumption: milestones are unique on (code, scope_key)
 * with no user dimension — a second administrator's identical achievement
 * would be silently swallowed by firstOrCreate.
 */
class EvaluateMilestones
{
    /** @var list<int> */
    private const array PUBLICATION_THRESHOLDS = [1, 5, 10, 25, 50, 100];

    /** @var list<int> */
    private const array READER_THRESHOLDS = [1, 100, 1000, 10000];

    /** @var list<int> */
    private const array MEANINGFUL_READER_THRESHOLDS = [1, 100, 1000];

    /** @return Collection<int, AnalyticsMilestone> */
    public function handle(User $user): Collection
    {
        $achieved = [];
        $published = AuthorPublication::query()->where('author_id', $user->id)->count();

        foreach (self::PUBLICATION_THRESHOLDS as $threshold) {
            if ($published >= $threshold) {
                $achieved[] = $this->record($user, "published_{$threshold}_posts", 'site', ['posts' => $published]);
            }
        }

        $maxReaders = AnalyticsPeriodSnapshot::query()->where('scope_key', 'site')->max('readers');
        $maxMeaningful = AnalyticsPeriodSnapshot::query()->where('scope_key', 'site')->max('meaningful_readers');
        $readers = is_numeric($maxReaders) ? (int) $maxReaders : 0;
        $meaningful = is_numeric($maxMeaningful) ? (int) $maxMeaningful : 0;

        foreach (self::READER_THRESHOLDS as $threshold) {
            if ($readers >= $threshold) {
                $achieved[] = $this->record($user, "measured_readers_{$threshold}", 'site', ['readers' => $readers]);
            }
        }
        foreach (self::MEANINGFUL_READER_THRESHOLDS as $threshold) {
            if ($meaningful >= $threshold) {
                $achieved[] = $this->record($user, "meaningful_readers_{$threshold}", 'site', ['meaningful_readers' => $meaningful]);
            }
        }

        $fiftyPercent = AnalyticsPeriodSnapshot::query()
            ->where('scope_key', 'site')
            ->where('readers', '>=', 50)
            ->whereRaw('meaningful_readers * 2 >= readers')
            ->first();
        if ($fiftyPercent !== null) {
            $achieved[] = $this->record($user, 'first_50_percent_meaningful_rate', 'site', [
                'readers' => $fiftyPercent->readers,
                'rate' => round(($fiftyPercent->meaningful_readers / $fiftyPercent->readers) * 100, 1),
            ]);
        }

        if (AnalyticsPeriodSnapshot::query()->where('shares', '>', 0)->exists()) {
            $achieved[] = $this->record($user, 'first_measured_share', 'site');
        }

        foreach ([90, 365] as $ageDays) {
            $oldPostScopes = Post::query()
                ->where('published_at', '<=', now()->subDays($ageDays))
                ->get(['id'])
                ->map(static fn (Post $post): string => 'post:'.$post->id)
                ->values()
                ->all();
            $hasReaders = $oldPostScopes !== [] && AnalyticsPeriodSnapshot::query()
                ->whereIn('scope_key', $oldPostScopes)
                ->where('readers', '>', 0)
                ->exists();
            if ($hasReaders) {
                $achieved[] = $this->record($user, "readers_after_{$ageDays}_days", 'site');
            }
        }

        foreach ($this->monthlyRecords($user) as $record) {
            $achieved[] = $this->record(
                $user,
                'new_monthly_post_record',
                'month:'.$record['month'],
                ['posts' => $record['count']],
            );
        }

        return collect($achieved)->unique('id')->values();
    }

    /** @return Collection<int, array{month: string, count: int<0, max>}> */
    private function monthlyRecords(User $user): Collection
    {
        $monthly = AuthorPublication::query()
            ->where('author_id', $user->id)
            ->oldest('first_published_at')
            ->get(['first_published_at'])
            ->groupBy(fn (AuthorPublication $publication): string => CarbonImmutable::instance($publication->first_published_at)->format('Y-m'))
            ->map(fn (Collection $publications, string $month): array => ['month' => $month, 'count' => $publications->count()])
            ->values();
        $record = 0;

        return $monthly->filter(function (array $month) use (&$record): bool {
            if ($month['count'] <= $record) {
                return false;
            }

            $wasRecord = $record > 0;
            $record = $month['count'];

            return $wasRecord;
        });
    }

    /** @param array<string, bool|float|int|string|null>|null $evidence */
    private function record(User $user, string $code, string $scopeKey, ?array $evidence = null): AnalyticsMilestone
    {
        return AnalyticsMilestone::query()->firstOrCreate(
            ['code' => $code, 'scope_key' => $scopeKey],
            ['user_id' => $user->id, 'evidence' => $evidence, 'achieved_at' => now()],
        );
    }
}
