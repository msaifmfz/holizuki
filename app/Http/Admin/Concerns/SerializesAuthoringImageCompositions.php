<?php

declare(strict_types=1);

namespace App\Http\Admin\Concerns;

use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\Storage;

trait SerializesAuthoringImageCompositions
{
    /** @return array<string, mixed> */
    private function authoringImageCompositionData(
        AuthoringImageComposition $composition,
        bool $passageStale,
    ): array {
        $composition->loadMissing(['plan', 'media', 'selectedPassage']);
        $usedMediaIds = $composition->post()
            ->firstOrFail()
            ->draft()
            ->firstOrFail()
            ->body
            ?->referencedMediaIds() ?? [];

        return [
            'id' => $composition->public_id,
            'plan_id' => $composition->plan?->public_id,
            'usage' => $composition->usage->value,
            'section_heading' => $composition->section_heading,
            'position' => $composition->position,
            'passage_markdown' => $composition->passage_markdown,
            'selected_passage_id' => $composition->selectedPassage?->public_id,
            'passage_stale' => $passageStale,
            'media' => $composition->media
                ->map(fn (PostMedia $media): array => [
                    'id' => $media->id,
                    'url' => Storage::disk('public')->url($media->path),
                    'width' => $media->width,
                    'height' => $media->height,
                    'display_name' => $media->display_name,
                    'alt_text' => $media->alt_text,
                    'caption' => $media->caption,
                    'role' => $media->role->value,
                    'placement' => $media->placement->value,
                    'status' => $media->status->value,
                    'story_priority' => in_array($media->id, $usedMediaIds, true)
                        ? 'required'
                        : $media->story_priority->value,
                    'story_notes' => $media->story_notes,
                    'used_in_draft' => in_array($media->id, $usedMediaIds, true),
                    'error' => null,
                ])
                ->all(),
            'updated_at' => $composition->updated_at?->toISOString(),
        ];
    }
}
