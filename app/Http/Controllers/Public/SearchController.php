<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    use BuildsPublicPostCards;

    public function __invoke(Request $request): Response
    {
        $term = $request->query('q');
        $query = is_string($term) ? Str::limit(trim($term), 100, '') : '';

        $posts = $query === ''
            ? new LengthAwarePaginator([], 0, 12, 1, ['path' => $request->url()])
            : $this->publicPostQuery()
                ->publicSearch($query)
                ->paginate(12)
                ->withQueryString()
                ->through(fn (Post $post): array => $this->postCard($post));

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
