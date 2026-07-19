<?php

declare(strict_types=1);

namespace App\Domain\Reading\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Support\Facades\Storage;

class BuildReaderDocument
{
    /**
     * @return array{document: array<string, mixed>|null, table_of_contents: list<array{id: string, title: string, level: int}>}
     */
    public function handle(Post $post): array
    {
        $mediaIds = $post->body?->referencedMediaIds() ?? [];
        $media = $mediaIds === []
            ? []
            : PostMedia::query()
                ->whereBelongsTo($post)
                ->whereKey($mediaIds)
                ->get(['id', 'path', 'width', 'height'])
                ->mapWithKeys(fn (PostMedia $item): array => [
                    $item->id => [
                        'url' => Storage::disk('public')->url($item->path),
                        'width' => $item->width,
                        'height' => $item->height,
                    ],
                ])
                ->all();

        return $post->body?->readerDocument($media) ?? ['document' => null, 'table_of_contents' => []];
    }
}
