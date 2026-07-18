<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

class CategoryPageController extends Controller
{
    use BuildsPublicPostCards;

    public function show(Category $category): Response
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
                canonical: route('public.categories.show', $category->slug),
            ),
        ]);
    }
}
