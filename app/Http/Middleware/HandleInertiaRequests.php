<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\PostStatus;
use App\Models\Category;
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
