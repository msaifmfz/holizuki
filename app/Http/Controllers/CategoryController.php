<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\ResolvesUniqueSlug;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Support\PublicCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    use ResolvesUniqueSlug;

    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        return Inertia::render('categories/index', [
            'categories' => Category::query()
                ->withCount('posts')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'posts_count' => $category->posts_count,
                ]),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $name = $request->string('name')->toString();

        Category::create([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Category::class),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $name = $request->string('name')->toString();

        $category->update([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Category::class, $category->id),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
        ]);

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        PublicCache::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index');
    }
}
