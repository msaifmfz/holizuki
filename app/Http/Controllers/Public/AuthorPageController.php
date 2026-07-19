<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthorPageController extends Controller
{
    use BuildsPublicPostCards;

    public function show(Request $request, User $user): Response
    {
        $posts = $this->publicPostQuery()
            ->where('author_id', $user->id)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

        return Inertia::render('public/authors/show', [
            'author' => [
                ...$this->authorProfile($user),
                'posts_count' => $posts->total(),
            ],
            'posts' => $posts,
            'seo' => Seo::make(
                title: $user->name.' — '.Seo::siteName(),
                description: $user->bio,
                canonical: route('public.authors.show', array_filter([
                    'user' => (string) $user->author_slug,
                    'page' => $request->integer('page', 1) > 1 ? $request->integer('page') : null,
                ], static fn (mixed $value): bool => $value !== null)),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
            ),
        ]);
    }
}
