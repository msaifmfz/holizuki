<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Publishing\Models\PostSource;

trait SerializesPostSource
{
    /** @return array<string, mixed> */
    private function serializePostSource(PostSource $source): array
    {
        return [
            'id' => $source->id,
            'kind' => $source->kind->value,
            'title' => $source->title,
            'url' => $source->url,
            'content' => $source->content,
            'original_name' => $source->original_name,
            'mime_type' => $source->mime_type,
            'size_bytes' => $source->size_bytes,
            'visibility' => $source->visibility->value,
            'approved' => $source->isApproved(),
            'approved_at' => $source->approved_at?->toISOString(),
            'created_at' => $source->created_at?->toISOString(),
            'updated_at' => $source->updated_at?->toISOString(),
        ];
    }
}
