<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Queries\PublicPostCards;
use App\Domain\Reading\Support\Seo;
use App\Domain\Taxonomy\Models\Category;
use App\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryPageController extends Controller
{
    public function __construct(private readonly PublicPostCards $postCards) {}

    public function show(Request $request, Category $category): Response
    {
        $posts = $this->postCards->query()
            ->where('category_id', $category->id)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCards->card($post));

        return Inertia::render('public/categories/show', [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'posts_count' => $posts->total(),
            ],
            'posts' => $posts,
            'seo' => Seo::make(
                title: $category->name.' — '.Seo::siteName(),
                description: $category->description,
                canonical: route('public.categories.show', array_filter([
                    'category' => $category->slug,
                    'page' => $request->integer('page', 1) > 1 ? $request->integer('page') : null,
                ], static fn (mixed $value): bool => $value !== null)),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
            ),
        ]);
    }
}
