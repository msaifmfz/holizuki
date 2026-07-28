<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\Storage;

trait SerializesPostMedia
{
    /** @return array<string, mixed> */
    private function serializePostMedia(PostMedia $media, bool $usedInDraft = false): array
    {
        return [
            'id' => $media->id,
            'url' => Storage::disk('public')->url($media->path),
            'width' => $media->width,
            'height' => $media->height,
            'display_name' => $media->display_name,
            'alt_text' => $media->alt_text,
            'caption' => $media->caption,
            'role' => $media->role->value,
            'placement' => $media->placement->value,
            'origin' => $media->origin->value,
            'status' => $media->status->value,
            'story_priority' => $usedInDraft ? 'required' : $media->story_priority->value,
            'story_notes' => $media->story_notes,
            'used_in_draft' => $usedInDraft,
            'created_at' => $media->created_at?->toISOString(),
            'updated_at' => $media->updated_at?->toISOString(),
        ];
    }
}
