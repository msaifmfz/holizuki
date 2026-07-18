<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait ResolvesUniqueSlug
{
    /**
     * Derive a URL-safe slug from the given name, suffixing until unique.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveUniqueSlug(string $name, string $modelClass, ?int $ignoreId = null, string $column = 'slug'): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'untitled';
        }

        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where($column, $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
