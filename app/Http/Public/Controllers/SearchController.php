<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    public function __invoke(Request $request): Response
    {
        $term = $request->query('q');
        $query = is_string($term) ? Str::limit(trim($term), 100, '') : '';

        $posts = $query === ''
            ? new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()])
            : $this->postCards->query()
                ->publicSearch($query)
                ->paginate(12)
                ->withQueryString()
                ->through(fn (Post $post): array => $this->postCards->card($post));

        return Inertia::render('public/search', [
            'query' => $query,
            'posts' => $posts,
            'seo' => Seo::make(
                title: 'Search — '.Seo::siteName(),
                canonical: route('public.search'),
                robots: 'noindex,follow',
            ),
        ]);
    }
}
