<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Reading\Support\Seo;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class TopicController extends Controller
{
    public function __invoke(): Response
    {
        $publishedPosts = static function (Builder $query): void {
            $query->where('status', PostStatus::Published);
        };

        return Inertia::render('public/topics', [
            'categories' => Category::query()
                ->whereHas('posts', $publishedPosts)
                ->withCount(['posts' => $publishedPosts])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'description'])
                ->map(fn (Category $category): array => [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'posts_count' => $category->posts_count,
                ]),
            'tags' => Tag::query()
                ->whereHas('posts', $publishedPosts)
                ->withCount(['posts' => $publishedPosts])
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Tag $tag): array => [
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'posts_count' => $tag->posts_count,
                ]),
            'seo' => Seo::make(
                title: 'Topics — '.Seo::siteName(),
                description: 'Browse posts by category and tag.',
                canonical: route('public.topics'),
            ),
        ]);
    }
}
