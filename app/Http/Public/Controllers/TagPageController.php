<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TagPageController extends Controller
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    public function show(Request $request, Tag $tag): Response
    {
        $posts = $this->postCards->query()
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCards->card($post));

        return Inertia::render('public/tags/show', [
            'tag' => [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'posts_count' => $posts->total(),
            ],
            'posts' => $posts,
            'seo' => Seo::make(
                title: '#'.$tag->name.' — '.Seo::siteName(),
                description: 'Posts tagged “'.$tag->name.'”.',
                canonical: route('public.tags.show', array_filter([
                    'tag' => $tag->slug,
                    'page' => $request->integer('page', 1) > 1 ? $request->integer('page') : null,
                ], static fn (mixed $value): bool => $value !== null)),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
            ),
        ]);
    }
}
