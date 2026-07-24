<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Markdown\MarkdownSerializer;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use UnexpectedValueException;

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

    public function rootFor(Post $post): string
    {
        return config()->string('assistant.workspaces', storage_path('app/ai-workspaces')).'/'.$post->id;
    }

    public function pathFor(Post $post, AssistantTurn $turn): string
    {
        return $this->pathForRun($post, $turn->run_token);
    }

    public function pathForRun(Post $post, string $runToken): string
    {
        if (! Str::isUuid($runToken)) {
            throw new UnexpectedValueException('Assistant run tokens must be UUIDs.');
        }

        return $this->rootFor($post).'/runs/'.$runToken;
    }

    public function materialize(Post $post, AssistantTurn $turn): void
    {
        $path = $this->pathForRun($post, $turn->run_token);
        $root = $this->rootFor($post);
        $runs = $root.'/runs';

        $this->files->ensureDirectoryExists($runs);
        $this->files->chmod($root, 0700);
        $this->files->chmod($runs, 0700);

        foreach ($this->files->directories($runs) as $runDirectory) {
            if (! is_string($runDirectory)) {
                continue;
            }

            if (basename($runDirectory) !== $turn->run_token) {
                $this->files->deleteDirectory($runDirectory);
            }
        }

        $this->files->deleteDirectory($path);
        $this->files->ensureDirectoryExists($path.'/images');
        $this->files->chmod($path, 0700);
        $this->files->chmod($path.'/images', 0700);

        $draft = $this->draftMarkdown($post);
        $this->assertSize('draft.md', $draft, $this->draftLimit());
        $this->putPrivate($path.'/draft.md', $draft);
        $this->putPrivate($path.'/meta.json', $this->encodeJson($this->metaFor($post)));

        $session = $turn->session()->firstOrFail();

        $startsNewOutline = $turn->task_type === AssistantTaskType::Outline
            && (($turn->context ?? [])['step'] ?? null) === 'start';

        if (! $startsNewOutline && $session->outline_ready && $session->outline_markdown !== null) {
            $this->putPrivate($path.'/outline.md', $session->outline_markdown);
        }

        if (in_array($turn->task_type, [AssistantTaskType::Metadata, AssistantTaskType::Transform], true)) {
            $this->writeImageManifest($path, []);

            return;
        }

        $this->materializeImages($post, $path);
    }

    public function destroy(Post $post): bool
    {
        return $this->files->deleteDirectory($this->rootFor($post));
    }

    public function draftMarkdown(Post $post): string
    {
        return $post->body instanceof RichTextDocument ? $this->serializer->serialize($post->body) : '';
    }

    public function destroyRun(Post $post, string $runToken): bool
    {
        return $this->files->deleteDirectory($this->pathForRun($post, $runToken));
    }

    public function readDraft(Post $post, AssistantTurn $turn): string
    {
        $draft = $this->readRegularFile(
            $this->pathForRun($post, $turn->run_token).'/draft.md',
            $this->draftLimit(),
        );

        if (! mb_check_encoding($draft, 'UTF-8')) {
            throw new UnexpectedValueException('The assistant draft must be valid UTF-8.');
        }

        return $draft;
    }

    /** @return array<string, mixed> */
    public function readMeta(Post $post, AssistantTurn $turn): array
    {
        $file = $this->pathForRun($post, $turn->run_token).'/meta.json';
        $contents = $this->readRegularFile($file, $this->metaLimit());

        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The assistant metadata must be valid JSON.', $exception->getCode(), previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('The assistant metadata must be a JSON object.');
        }

        $unknownKeys = array_diff(array_keys($decoded), self::META_KEYS);

        if ($unknownKeys !== []) {
            throw new UnexpectedValueException('The assistant metadata contains unsupported fields.');
        }

        $meta = [];

        foreach (self::META_KEYS as $key) {
            if (! array_key_exists($key, $decoded)) {
                continue;
            }

            $value = $decoded[$key];

            if ($key === 'tags') {
                if (! is_array($value) || ! array_is_list($value)) {
                    throw new UnexpectedValueException('Assistant tags must be a JSON array.');
                }

                foreach ($value as $tag) {
                    if (! is_string($tag)) {
                        throw new UnexpectedValueException('Every assistant tag must be a string.');
                    }
                }

                $meta[$key] = $value;

                continue;
            }

            if ($value !== null && ! is_string($value)) {
                throw new UnexpectedValueException('Assistant metadata values must be strings or null.');
            }

            $meta[$key] = $value;
        }

        return $meta;
    }

    public function readOutline(Post $post, AssistantTurn $turn): ?string
    {
        $file = $this->pathForRun($post, $turn->run_token).'/outline.md';

        if (! $this->files->exists($file)) {
            return null;
        }

        $outline = $this->readRegularFile($file, $this->outlineLimit());

        if (! mb_check_encoding($outline, 'UTF-8') || preg_match('/^##\s+\S/mu', $outline) !== 1) {
            throw new UnexpectedValueException('The assistant outline must contain at least one section heading.');
        }

        return rtrim($outline)."\n";
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
        /** @var list<array{source: string, destination: string, manifest: array<string, mixed>}> $images */
        $images = [];
        $totalBytes = 0;
        $usedMediaIds = $post->body instanceof RichTextDocument ? $post->body->referencedMediaIds() : [];

        foreach ($post->media as $media) {
            $extension = $this->extensionFor($media->path);
            $filename = 'media-'.$media->id.($extension === '' ? '' : '.'.$extension);
            $source = $this->imageSource($media->path);

            if ($source === null) {
                continue;
            }

            $this->assertImageCount($images);
            $totalBytes += $source['bytes'];
            $this->assertImageBytes($totalBytes);

            $images[] = [
                'source' => $source['path'],
                'destination' => $path.'/images/'.$filename,
                'manifest' => [
                    'file' => 'images/'.$filename,
                    'role' => 'inline',
                    'mediaId' => $media->id,
                    'width' => $media->width,
                    'height' => $media->height,
                    'usedInDraft' => in_array($media->id, $usedMediaIds, true),
                ],
            ];
        }

        if ($post->featured_image_path !== null) {
            $extension = $this->extensionFor($post->featured_image_path);
            $filename = 'featured'.($extension === '' ? '' : '.'.$extension);
            $source = $this->imageSource($post->featured_image_path);

            if ($source !== null) {
                $this->assertImageCount($images);
                $totalBytes += $source['bytes'];
                $this->assertImageBytes($totalBytes);

                $images[] = [
                    'source' => $source['path'],
                    'destination' => $path.'/images/'.$filename,
                    'manifest' => [
                        'file' => 'images/'.$filename,
                        'role' => 'featured',
                        'mediaId' => null,
                        'width' => null,
                        'height' => null,
                        'usedInDraft' => false,
                    ],
                ];
            }
        }

        $manifest = [];

        foreach ($images as $image) {
            $this->copyImage($image['source'], $image['destination']);
            $manifest[] = $image['manifest'];
        }

        $this->writeImageManifest($path, $manifest);
    }

    private function extensionFor(string $storagePath): string
    {
        $extension = Str::lower(pathinfo($storagePath, PATHINFO_EXTENSION));

        return Str::isMatch('/\A[a-z0-9]{1,10}\z/', $extension) ? $extension : '';
    }

    /** @return array{path: string, bytes: int}|null */
    private function imageSource(string $storagePath): ?array
    {
        $source = Storage::disk('public')->path($storagePath);
        $root = realpath(Storage::disk('public')->path(''));
        $realSource = realpath($source);

        if ($realSource === false) {
            return null;
        }

        if (
            $root === false
            || ! str_starts_with($realSource, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || is_link($source)
            || ! is_file($realSource)
        ) {
            throw new UnexpectedValueException('Assistant images must be regular files on the public disk.');
        }

        $bytes = filesize($realSource);

        if (! is_int($bytes)) {
            throw new UnexpectedValueException('The assistant image size could not be read.');
        }

        return ['path' => $realSource, 'bytes' => $bytes];
    }

    private function copyImage(string $source, string $destination): void
    {
        if (! $this->files->copy($source, $destination)) {
            throw new UnexpectedValueException('The assistant image could not be copied.');
        }

        $this->files->chmod($destination, 0444);
    }

    /** @param list<array<string, mixed>> $manifest */
    private function writeImageManifest(string $path, array $manifest): void
    {
        $manifestPath = $path.'/images/manifest.json';
        $this->files->put($manifestPath, $this->encodeJson($manifest));
        $this->files->chmod($manifestPath, 0444);
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
        try {
            return json_encode(
                $value,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            )."\n";
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The workspace metadata could not be encoded.', $exception->getCode(), previous: $exception);
        }
    }

    private function putPrivate(string $path, string $contents): void
    {
        if ($this->files->put($path, $contents) === false) {
            throw new UnexpectedValueException('An assistant workspace file could not be written.');
        }

        $this->files->chmod($path, 0600);
    }

    private function readRegularFile(string $path, int $maximumBytes): string
    {
        $workspaceRoot = realpath(config()->string('assistant.workspaces', storage_path('app/ai-workspaces')));
        $realPath = realpath($path);
        $parent = dirname($path);

        if (
            $workspaceRoot === false
            || $realPath === false
            || ! str_starts_with($realPath, rtrim($workspaceRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || is_link($parent)
            || is_link($path)
            || ! is_file($realPath)
        ) {
            throw new UnexpectedValueException('Assistant output must be a regular file.');
        }

        $bytes = filesize($realPath);

        if (! is_int($bytes) || $bytes > $maximumBytes) {
            throw new UnexpectedValueException('Assistant output exceeded its size limit.');
        }

        return $this->files->get($realPath);
    }

    private function assertSize(string $name, string $contents, int $maximumBytes): void
    {
        if (strlen($contents) > $maximumBytes) {
            throw new UnexpectedValueException($name.' exceeded its workspace size limit.');
        }
    }

    /** @param list<array<string, mixed>> $manifest */
    private function assertImageCount(array $manifest): void
    {
        if (count($manifest) >= config()->integer('assistant.limits.image_count', 20)) {
            throw new UnexpectedValueException('The assistant workspace has too many images.');
        }
    }

    private function assertImageBytes(int $totalBytes): void
    {
        if ($totalBytes > config()->integer('assistant.limits.image_bytes', 100 * 1024 * 1024)) {
            throw new UnexpectedValueException('The assistant workspace images exceeded their size limit.');
        }
    }

    private function draftLimit(): int
    {
        return config()->integer('assistant.limits.draft_bytes', 2 * 1024 * 1024);
    }

    private function metaLimit(): int
    {
        return config()->integer('assistant.limits.meta_bytes', 64 * 1024);
    }

    private function outlineLimit(): int
    {
        return config()->integer('assistant.limits.outline_bytes', 64 * 1024);
    }
}
