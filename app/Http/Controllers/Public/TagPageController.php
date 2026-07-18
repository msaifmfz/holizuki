<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Tag;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class TagPageController extends Controller
{
    use BuildsPublicPostCards;

    public function show(Tag $tag): Response
    {
        $posts = $this->publicPostQuery()
            ->whereHas('tags', fn (Builder $query) => $query->whereKey($tag->id))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

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
                canonical: route('public.tags.show', $tag->slug),
            ),
        ]);
    }
}
