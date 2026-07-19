<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Queries\PopularPosts;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly PublicPostCards $postCards,
        private readonly PopularPosts $popularPosts,
    ) {}

    public function index(Request $request): Response
    {
        $featured = $this->postCards->query()
            ->whereNotNull('featured_at')
            ->addSelect('featured_at')
            ->reorder()
            ->latest('featured_at')
            ->orderByDesc('id')
            ->limit(config()->integer('blog.maximum_featured_posts', 3))
            ->get();

        if ($featured->isEmpty()) {
            $newest = $this->postCards->query()->first();

            if ($newest !== null) {
                $featured->push($newest);
            }
        }

        $featuredIds = [];

        foreach ($featured as $featuredPost) {
            $featuredIds[] = $featuredPost->id;
        }

        $postsQuery = $this->postCards->query();

        if ($featuredIds !== []) {
            $postsQuery->whereKeyNot($featuredIds);
        }

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCards->card($post));

        $page = max(1, $request->integer('page', 1));

        return Inertia::render('public/home', [
            'featured' => $featured->map(fn (Post $post): array => $this->postCards->card($post))->values(),
            'popular' => $this->popularPosts->list($featuredIds),
            'posts' => $posts,
            'seo' => Seo::make(
                title: Seo::siteName().' — Blog',
                canonical: $page > 1 ? route('home', ['page' => $page]) : route('home'),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
                jsonLd: Seo::websiteJsonLd(),
            ),
        ]);
    }
}
