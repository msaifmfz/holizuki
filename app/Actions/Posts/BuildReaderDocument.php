<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Models\Post;
use App\Models\PostMedia;
use App\Support\RichTextDocument;
use Illuminate\Support\Facades\Storage;

class BuildReaderDocument
{
    /**
     * @return array{document: array<string, mixed>|null, table_of_contents: list<array{id: string, title: string, level: int}>}
     */
    public function handle(Post $post): array
    {
        $mediaIds = RichTextDocument::referencedMediaIds($post->body);
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

        return RichTextDocument::readerDocument($post->body, $media);
    }
}
