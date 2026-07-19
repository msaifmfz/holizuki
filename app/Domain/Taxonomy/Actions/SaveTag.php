<?php

declare(strict_types=1);

namespace App\Domain\Taxonomy\Actions;

use App\Domain\Taxonomy\Events\TagSaved;
use App\Domain\Taxonomy\Models\Tag;
use App\Support\Concerns\ResolvesUniqueSlug;

class SaveTag
{
    use ResolvesUniqueSlug;

    public function handle(Tag $tag, string $name): Tag
    {
        $tag->fill([
            'name' => $name,
            'slug' => $this->resolveUniqueSlug($name, Tag::class, $tag->id),
        ]);
        $tag->save();

        event(new TagSaved($tag));

        return $tag;
    }
}
