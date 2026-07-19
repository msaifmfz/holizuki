<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthorPageController extends Controller
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    public function show(Request $request, User $user): Response
    {
        $posts = $this->postCards->query()
            ->where('author_id', $user->id)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCards->card($post));

        return Inertia::render('public/authors/show', [
            'author' => [
                ...$this->postCards->authorProfile($user),
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
