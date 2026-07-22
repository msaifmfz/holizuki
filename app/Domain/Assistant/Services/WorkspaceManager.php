<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Publishing\Markdown\MarkdownSerializer;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Materializes the on-disk workspace an agent turn operates on: the draft as
 * markdown, editable metadata as JSON, and the post's images. The workspace
 * is rebuilt from the live post before every turn so the author's latest
 * autosaved state always wins as the base the agent edits.
 */
class WorkspaceManager
{
    /** @var list<string> */
    public const array META_KEYS = [
        'title', 'slug', 'excerpt', 'seo_title', 'meta_description',
        'og_title', 'og_description', 'tags', 'category',
        'featured_image_alt', 'featured_image_caption',
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly MarkdownSerializer $serializer,
    ) {}

    public function pathFor(Post $post): string
    {
        return config()->string('assistant.workspaces', storage_path('app/ai-workspaces')).'/'.$post->id;
    }

    public function materialize(Post $post): void
    {
        $path = $this->pathFor($post);

        // Rebuild images/ from scratch so media removed from the post since the
        // last turn leaves no orphan files or stale manifest behind. Agent-
        // authored files elsewhere in the workspace (e.g. outline.md) survive.
        $this->files->deleteDirectory($path.'/images');
        $this->files->ensureDirectoryExists($path.'/images');

        $this->files->put($path.'/draft.md', $this->draftMarkdown($post));
        $this->files->put($path.'/meta.json', $this->encodeJson($this->metaFor($post)));
        $this->materializeImages($post, $path);
    }

    public function destroy(Post $post): void
    {
        $this->files->deleteDirectory($this->pathFor($post));
    }

    public function draftMarkdown(Post $post): string
    {
        return $post->body instanceof RichTextDocument ? $this->serializer->serialize($post->body) : '';
    }

    public function readDraft(Post $post): string
    {
        $file = $this->pathFor($post).'/draft.md';

        return $this->files->exists($file) ? $this->files->get($file) : '';
    }

    /** @return array<string, mixed> */
    public function readMeta(Post $post): array
    {
        $file = $this->pathFor($post).'/meta.json';

        if (! $this->files->exists($file)) {
            return [];
        }

        $decoded = json_decode($this->files->get($file), true);

        if (! is_array($decoded)) {
            return [];
        }

        $meta = [];

        foreach (self::META_KEYS as $key) {
            if (! array_key_exists($key, $decoded)) {
                continue;
            }

            $value = $decoded[$key];

            if ($key === 'tags') {
                $meta[$key] = is_array($value)
                    ? array_values(array_filter($value, is_string(...)))
                    : [];

                continue;
            }

            if ($value === null || is_string($value)) {
                $meta[$key] = $value;
            }
        }

        return $meta;
    }

    /** @return array<string, mixed> */
    public function metaFor(Post $post): array
    {
        $post->load(['tags', 'category']);

        return [
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'seo_title' => $post->seo_title,
            'meta_description' => $post->meta_description,
            'og_title' => $post->og_title,
            'og_description' => $post->og_description,
            'tags' => $post->tags->pluck('name')->values()->all(),
            'category' => $post->category?->name,
            'featured_image_alt' => $post->featured_image_alt,
            'featured_image_caption' => $post->featured_image_caption,
        ];
    }

    /**
     * Media attributes for the markdown parser, keyed by media id. Sources
     * come from the current body's image nodes when present (byte-stable
     * round trips) and fall back to the public storage URL.
     *
     * @return array<int, array{src: string, width: int|null, height: int|null}>
     */
    public function mediaMap(Post $post): array
    {
        $map = [];

        foreach ($post->media as $media) {
            $map[$media->id] = [
                'src' => Storage::disk('public')->url($media->path),
                'width' => $media->width,
                'height' => $media->height,
            ];
        }

        if ($post->body instanceof RichTextDocument) {
            $this->overlayBodyImageAttributes($post->body->toArray(), $map);
        }

        return $map;
    }

    private function materializeImages(Post $post, string $path): void
    {
        $manifest = [];
        $usedMediaIds = $post->body instanceof RichTextDocument ? $post->body->referencedMediaIds() : [];

        foreach ($post->media as $media) {
            $extension = $this->extensionFor($media->path);
            $filename = 'media-'.$media->id.($extension === '' ? '' : '.'.$extension);
            $this->linkImage($media->path, $path.'/images/'.$filename);

            $manifest[] = [
                'file' => 'images/'.$filename,
                'role' => 'inline',
                'mediaId' => $media->id,
                'width' => $media->width,
                'height' => $media->height,
                'usedInDraft' => in_array($media->id, $usedMediaIds, true),
            ];
        }

        if ($post->featured_image_path !== null) {
            $extension = pathinfo($post->featured_image_path, PATHINFO_EXTENSION);
            $filename = 'featured'.($extension === '' ? '' : '.'.$extension);
            $this->linkImage($post->featured_image_path, $path.'/images/'.$filename);

            $manifest[] = [
                'file' => 'images/'.$filename,
                'role' => 'featured',
                'mediaId' => null,
                'width' => null,
                'height' => null,
                'usedInDraft' => false,
            ];
        }

        $this->files->put($path.'/images/manifest.json', $this->encodeJson($manifest));
    }

    private function extensionFor(string $storagePath): string
    {
        return pathinfo($storagePath, PATHINFO_EXTENSION);
    }

    private function linkImage(string $storagePath, string $destination): void
    {
        $source = Storage::disk('public')->path($storagePath);

        if ($this->files->exists($source) && ! $this->files->exists($destination) && ! @link($source, $destination)) {
            $this->files->copy($source, $destination);
        }
    }

    /**
     * @param  array<mixed>  $node
     * @param  array<int, array{src: string, width: int|null, height: int|null}>  $map
     */
    private function overlayBodyImageAttributes(array $node, array &$map): void
    {
        if (($node['type'] ?? null) === 'image' && is_array($node['attrs'] ?? null)) {
            $attributes = $node['attrs'];
            $mediaId = $attributes['mediaId'] ?? null;

            if (is_int($mediaId) && is_string($attributes['src'] ?? null)) {
                $map[$mediaId] = [
                    'src' => $attributes['src'],
                    'width' => is_int($attributes['width'] ?? null) ? $attributes['width'] : ($map[$mediaId]['width'] ?? null),
                    'height' => is_int($attributes['height'] ?? null) ? $attributes['height'] : ($map[$mediaId]['height'] ?? null),
                ];
            }
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $this->overlayBodyImageAttributes($child, $map);
            }
        }
    }

    /** @param array<mixed> $value */
    private function encodeJson(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }
}
