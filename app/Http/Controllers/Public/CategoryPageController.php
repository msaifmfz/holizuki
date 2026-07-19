<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryPageController extends Controller
{
    use BuildsPublicPostCards;

    public function show(Request $request, Category $category): Response
    {
        $posts = $this->publicPostQuery()
            ->where('category_id', $category->id)
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));

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
