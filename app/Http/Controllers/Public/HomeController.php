<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use BuildsPublicPostCards;

    public function index(Request $request): Response
    {
        $featured = $this->publicPostQuery()->first();

        $postsQuery = $this->publicPostQuery();

        if ($featured !== null) {
            $postsQuery->whereKeyNot($featured->id);
        }

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

        $page = max(1, $request->integer('page', 1));

        return Inertia::render('public/home', [
            'featured' => $featured === null ? null : $this->postCard($featured),
            'posts' => $posts,
            'seo' => Seo::make(
                title: Seo::siteName().' — Blog',
                canonical: $page > 1 ? route('home', ['page' => $page]) : route('home'),
                jsonLd: Seo::websiteJsonLd(),
            ),
        ]);
    }
}
