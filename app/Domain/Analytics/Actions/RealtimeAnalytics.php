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
                ['customEvent:content_key'],
                ['activeUsers'],
                limit: 20,
            ));
            $activePostReaders = [];
            $siteRow = $sitePage->rows[0] ?? null;
            $readers = $siteRow === null
                ? 0
                : (int) round($siteRow->metrics['activeUsers'] ?? 0);

            foreach ($page->rows as $row) {
                $contentKey = $row->dimensions['customEvent:content_key'] ?? '';
                $activeUsers = (int) round($row->metrics['activeUsers'] ?? 0);

                if (preg_match('/^post:\d+$/', $contentKey) === 1) {
                    $activePostReaders[$contentKey] = $activeUsers;
                }
            }
            $postIds = array_map(
                static fn (string $contentKey): int => (int) str($contentKey)->after('post:')->toString(),
                array_keys($activePostReaders),
            );
            $titles = Post::query()->whereKey($postIds)->pluck('title', 'id');
            $posts = [];
            foreach ($activePostReaders as $contentKey => $activeUsers) {
                $postId = (int) str($contentKey)->after('post:')->toString();
                $title = $titles->get($postId);
                $posts[] = [
                    'contentKey' => $contentKey,
                    'title' => is_string($title) ? $title : null,
                    'readers' => $activeUsers,
                ];
            }

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
