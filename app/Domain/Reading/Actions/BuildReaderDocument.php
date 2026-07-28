<?php

declare(strict_types=1);

namespace App\Domain\Reading\Actions;

use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use App\Domain\Publishing\Models\PostSource;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildReaderDocument
{
    /**
     * The live article as readers see it: only sources the author approved and
     * marked public are credited.
     *
     * @return array{
     *     document: array<string, mixed>|null,
     *     table_of_contents: list<array{id: string, title: string, level: int}>,
     *     citations: list<array{number: int, source_id: int, title: string, kind: string, url: string|null}>
     * }
     */
    public function forReaders(Post $post): array
    {
        return $this->build($post, $post->body, includeUnapprovedSources: false);
    }

    /**
     * An author previewing their own working draft or an old revision, where
     * citations to not-yet-approved sources should still be visible so the gaps
     * are obvious before publishing.
     *
     * @return array{
     *     document: array<string, mixed>|null,
     *     table_of_contents: list<array{id: string, title: string, level: int}>,
     *     citations: list<array{number: int, source_id: int, title: string, kind: string, url: string|null}>
     * }
     */
    public function forAuthorPreview(Post $post, ?RichTextDocument $body): array
    {
        return $this->build($post, $body, includeUnapprovedSources: true);
    }

    /**
     * @return array{
     *     document: array<string, mixed>|null,
     *     table_of_contents: list<array{id: string, title: string, level: int}>,
     *     citations: list<array{number: int, source_id: int, title: string, kind: string, url: string|null}>
     * }
     */
    private function build(Post $post, ?RichTextDocument $body, bool $includeUnapprovedSources): array
    {
        $mediaIds = $body?->referencedMediaIds() ?? [];
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

        $sourceIds = $body?->referencedSourceIds() ?? [];
        $sources = $sourceIds === []
            ? []
            : PostSource::query()
                ->whereBelongsTo($post)
                ->whereKey($sourceIds)
                ->unless(
                    $includeUnapprovedSources,
                    fn (Builder $query): Builder => $query
                        ->whereNotNull('approved_at')
                        ->where('visibility', PostSourceVisibility::Public),
                )
                ->get(['id', 'kind', 'title', 'url'])
                ->mapWithKeys(fn (PostSource $source): array => [
                    $source->id => [
                        'title' => Str::of($source->title)->trim()->limit(255)->toString(),
                        'kind' => $source->kind->value,
                        'url' => $source->kind === PostSourceKind::Url
                            ? $this->safeSourceUrl($source->url)
                            : null,
                    ],
                ])
                ->all();

        return $body?->readerDocument($media, $sources) ?? [
            'document' => null,
            'table_of_contents' => [],
            'citations' => [],
        ];
    }

    private function safeSourceUrl(?string $url): ?string
    {
        if ($url === null || Str::length($url) > 2048) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ! in_array(Str::lower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! is_string($parts['host'] ?? null)
            || trim($parts['host']) === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }
}
