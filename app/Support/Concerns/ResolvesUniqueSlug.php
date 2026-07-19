<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\ValueObjects\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

trait ResolvesUniqueSlug
{
    /**
     * Derive a URL-safe slug from the given name, suffixing until unique.
     * Soft-deletable models are checked including trashed rows, because the
     * database unique index covers those rows too.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function resolveUniqueSlug(
        string $name,
        string $modelClass,
        ?int $ignoreId = null,
        string $column = 'slug',
        string $fallback = 'untitled',
    ): string {
        $base = Slug::fromName($name, $fallback);

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($modelClass, $column, $candidate->value, $ignoreId)) {
            $candidate = $base->withSuffix($suffix++);
        }

        return $candidate->value;
    }

    /** @param class-string<Model> $modelClass */
    private function slugExists(string $modelClass, string $column, string $slug, ?int $ignoreId): bool
    {
        $query = $modelClass::query()
            ->where($column, $slug)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId));

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query->exists();
    }
}
