<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Taxonomy\Actions\DeleteCategory;
use App\Domain\Taxonomy\Actions\SaveCategory;
use App\Domain\Taxonomy\Models\Category;
use App\Http\Admin\Requests\StoreCategoryRequest;
use App\Http\Admin\Requests\UpdateCategoryRequest;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
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

    public function store(StoreCategoryRequest $request, SaveCategory $saveCategory): RedirectResponse
    {
        $saveCategory->handle(
            new Category,
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('categories.index');
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category,
        SaveCategory $saveCategory,
    ): RedirectResponse {
        $saveCategory->handle(
            $category,
            $request->string('name')->toString(),
            $request->filled('description') ? $request->string('description')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('categories.index');
    }

    public function destroy(Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $deleteCategory->handle($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('categories.index');
    }
}
