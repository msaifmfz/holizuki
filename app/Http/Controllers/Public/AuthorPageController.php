<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

class AuthorPageController extends Controller
{
    use BuildsPublicPostCards;

    public function show(User $user): Response
    {
        $posts = $this->publicPostQuery()
            ->where('author_id', $user->id)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

        return Inertia::render('public/authors/show', [
            'author' => [
                'name' => $user->name,
                'slug' => $user->author_slug,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'social_links' => $user->social_links,
                'posts_count' => $posts->total(),
            ],
            'posts' => $posts,
            'seo' => Seo::make(
                title: $user->name.' — '.Seo::siteName(),
                description: $user->bio,
                canonical: route('public.authors.show', (string) $user->author_slug),
            ),
        ]);
    }
}
