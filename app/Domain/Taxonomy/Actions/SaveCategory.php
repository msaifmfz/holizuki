<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\Events\CategorySaved;
use App\Domain\Taxonomy\Models\Category;
use App\Support\Concerns\ResolvesUniqueSlug;

class SaveCategory
{
    use ResolvesUniqueSlug;

    public function handle(Category $category, string $name, ?string $description): Category
    {
        $category->fill([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Category::class, $category->id),
            'description' => $description,
        ]);
        $category->save();

        event(new CategorySaved($category));

        return $category;
    }
}
