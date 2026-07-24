<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Contracts\AnalyticsReportingGateway;
use App\Domain\Analytics\ValueObjects\AnalyticsReportRequest;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RealtimeAnalytics
{
    private const int ACTIVE_POST_LIMIT = 20;

    private const string POST_SCREEN_PATTERN = '/^\[post:([1-9]\d*)\](?:\s|$)/';

    private const int REALTIME_SCREEN_LIMIT = 10000;

    public function __construct(private readonly AnalyticsReportingGateway $gateway) {}

    /** @return array{available: bool, stale: bool, readers: int|null, activePosts: list<array{contentKey: string, title: string|null, readers: int}>, fetchedAt: string|null} */
    public function handle(): array
    {
        $version = Cache::get(
            'analytics:dashboard-cache-version',
            config()->integer('analytics.snapshot_cache_version'),
        );
        $key = 'analytics:realtime:v'.(is_int($version) ? $version : 1);
        $cached = $this->cachedResult(Cache::get($key));

        if ($cached !== null && $this->cacheAgeInSeconds($cached) <= config()->integer('analytics.realtime_cache_seconds')) {
            return [...$cached, 'stale' => false];
        }

        try {
            $sitePage = $this->gateway->realtime(new AnalyticsReportRequest(
                'today',
                'today',
                [],
                ['activeUsers'],
                limit: 1,
            ));
            $page = $this->gateway->realtime(new AnalyticsReportRequest(
                'today',
                'today',
                ['unifiedScreenName'],
                ['activeUsers'],
                limit: self::REALTIME_SCREEN_LIMIT,
            ));
            $activePostReaders = [];
            $siteRow = $sitePage->rows[0] ?? null;
            $readers = $siteRow === null
                ? 0
                : (int) round($siteRow->metrics['activeUsers'] ?? 0);

            foreach ($page->rows as $row) {
                $screenName = $row->dimensions['unifiedScreenName'] ?? '';
                $activeUsers = (int) round($row->metrics['activeUsers'] ?? 0);
                $matches = [];

                if (preg_match(self::POST_SCREEN_PATTERN, $screenName, $matches) !== 1) {
                    continue;
                }

                $postId = (int) $matches[1];
                $activePostReaders[$postId] = max($activePostReaders[$postId] ?? 0, $activeUsers);
            }
            $titles = Post::query()
                ->published()
                ->whereKey(array_keys($activePostReaders))
                ->pluck('title', 'id');
            $posts = [];
            foreach ($activePostReaders as $postId => $activeUsers) {
                $title = $titles->get($postId);
                if (! is_string($title)) {
                    continue;
                }

                $posts[] = [
                    'contentKey' => 'post:'.$postId,
                    'title' => $title,
                    'readers' => $activeUsers,
                ];
            }
            usort(
                $posts,
                static fn (array $left, array $right): int => $right['readers'] <=> $left['readers'],
            );
            $posts = array_slice($posts, 0, self::ACTIVE_POST_LIMIT);

            $result = [
                'available' => true,
                'stale' => false,
                'readers' => $readers,
                'activePosts' => $posts,
                'fetchedAt' => now()->toISOString(),
            ];
            Cache::put($key, $result, config()->integer('analytics.realtime_stale_seconds'));

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            if ($cached !== null && $this->cacheAgeInSeconds($cached) <= config()->integer('analytics.realtime_stale_seconds')) {
                return [...$cached, 'stale' => true];
            }

            return ['available' => false, 'stale' => false, 'readers' => null, 'activePosts' => [], 'fetchedAt' => null];
        }
    }

    /** @return array{available: bool, stale: bool, readers: int|null, activePosts: list<array{contentKey: string, title: string|null, readers: int}>, fetchedAt: string|null}|null */
    private function cachedResult(mixed $cached): ?array
    {
        if (
            ! is_array($cached)
            || ! isset($cached['available'], $cached['stale'], $cached['activePosts'])
            || ! is_bool($cached['available'])
            || ! is_bool($cached['stale'])
            || (! is_int($cached['readers'] ?? null) && ($cached['readers'] ?? null) !== null)
            || (! is_string($cached['fetchedAt'] ?? null) && ($cached['fetchedAt'] ?? null) !== null)
            || ! is_array($cached['activePosts'])
        ) {
            return null;
        }

        $activePosts = [];
        foreach ($cached['activePosts'] as $post) {
            if (
                ! is_array($post)
                || ! is_string($post['contentKey'] ?? null)
                || ! is_int($post['readers'] ?? null)
                || (! is_string($post['title'] ?? null) && ($post['title'] ?? null) !== null)
            ) {
                return null;
            }

            $activePosts[] = [
                'contentKey' => $post['contentKey'],
                'title' => $post['title'] ?? null,
                'readers' => $post['readers'],
            ];
        }

        return [
            'available' => $cached['available'],
            'stale' => $cached['stale'],
            'readers' => $cached['readers'] ?? null,
            'activePosts' => $activePosts,
            'fetchedAt' => $cached['fetchedAt'] ?? null,
        ];
    }

    /** @param array{fetchedAt: string|null} $cached */
    private function cacheAgeInSeconds(array $cached): float
    {
        if ($cached['fetchedAt'] === null) {
            return INF;
        }

        return CarbonImmutable::parse($cached['fetchedAt'])->diffInSeconds(now());
    }
}
