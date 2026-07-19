<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Taxonomy\Actions\DeleteTag;
use App\Domain\Taxonomy\Actions\SaveTag;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Admin\Requests\StoreTagRequest;
use App\Http\Admin\Requests\UpdateTagRequest;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
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

    public function store(StoreTagRequest $request, SaveTag $saveTag): RedirectResponse
    {
        $saveTag->handle(new Tag, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return to_route('tags.index');
    }

    public function update(UpdateTagRequest $request, Tag $tag, SaveTag $saveTag): RedirectResponse
    {
        $saveTag->handle($tag, $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag updated.')]);

        return to_route('tags.index');
    }

    public function destroy(Tag $tag, DeleteTag $deleteTag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $deleteTag->handle($tag);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return to_route('tags.index');
    }
}
