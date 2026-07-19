<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Taxonomy\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Override;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->append('avatar_url'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => function () use ($request): ?string {
                    if (! $request->hasSession()) {
                        return null;
                    }

                    $message = $request->session()->get('success');

                    return is_string($message) ? $message : null;
                },
                'commentSubmitted' => function () use ($request): ?int {
                    if (! $request->hasSession()) {
                        return null;
                    }

                    $commentId = $request->session()->get('comment_submitted');

                    return is_int($commentId) ? $commentId : null;
                },
            ],
            'community' => [
                'consentVersion' => config('community.consent_version'),
                'sharingMethods' => config('community.sharing_methods'),
            ],
            'analytics' => [
                'collectionEnabled' => config()->boolean('analytics.collection_enabled')
                    && (app()->isProduction() || config()->boolean('analytics.allow_non_production_collection')),
                'measurementId' => config('analytics.measurement_id'),
                'consentVersion' => config('analytics.consent_version'),
                'consentDays' => config()->integer('analytics.consent_days'),
            ],
            'footerCategories' => fn (): array => Cache::remember(
                Category::FOOTER_CACHE_KEY,
                600,
                fn (): array => Category::query()
                    ->whereHas('posts', fn (Builder $query) => $query->where('status', PostStatus::Published))
                    ->withCount(['posts' => fn (Builder $query) => $query->where('status', PostStatus::Published)])
                    ->orderByDesc('posts_count')
                    ->orderBy('name')
                    ->limit(6)
                    ->get(['id', 'name', 'slug'])
                    ->map(fn (Category $category): array => [
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ])
                    ->all(),
            ),
        ];
    }
}
