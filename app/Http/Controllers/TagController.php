<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\RebuildPostMetadata;
use App\Concerns\ResolvesUniqueSlug;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Post;
use App\Models\Tag;
use App\Support\PublicCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    use ResolvesUniqueSlug;

    public function index(): Response
    {
        Gate::authorize('viewAny', Tag::class);

        return Inertia::render('tags/index', [
            'tags' => Tag::query()
                ->withCount('posts')
                ->orderBy('name')
                ->get()
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'posts_count' => $tag->posts_count,
                ]),
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();

        Tag::create([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Tag::class),
        ]);

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return to_route('tags.index');
    }

    public function update(UpdateTagRequest $request, Tag $tag, RebuildPostMetadata $rebuildPostMetadata): RedirectResponse
    {
        $name = $request->string('name')->toString();

        $tag->update([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Tag::class, $tag->id),
        ]);

        $rebuildPostMetadata->handleQuery(
            Post::query()->whereHas('tags', fn (Builder $query): Builder => $query->whereKey($tag->id)),
        );

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag updated.')]);

        return to_route('tags.index');
    }

    public function destroy(Tag $tag, RebuildPostMetadata $rebuildPostMetadata): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $postIds = $tag->posts()->pluck((new Post)->qualifyColumn('id'));
        $tag->delete();
        $rebuildPostMetadata->handleQuery(Post::query()->whereKey($postIds));

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return to_route('tags.index');
    }
}
