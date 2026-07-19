<?php

declare(strict_types=1);

namespace App\Domain\Publishing\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * A TipTap/ProseMirror document. Wraps the raw node tree so reading-time,
 * search text, media references, and the public reader document are derived
 * in one place.
 *
 * @implements Arrayable<array-key, mixed>
 */
final readonly class RichTextDocument implements Arrayable, JsonSerializable
{
    public const int WORDS_PER_MINUTE = 225;

    /** @var list<string> */
    public const array CODE_LANGUAGES = [
        'plaintext',
        'bash',
        'css',
        'diff',
        'html',
        'javascript',
        'json',
        'markdown',
        'php',
        'python',
        'sql',
        'typescript',
        'xml',
    ];

    /** @param array<mixed> $document */
    private function __construct(private array $document) {}

    /** @param array<mixed> $document */
    public static function fromArray(array $document): self
    {
        return new self($document);
    }

    /** @return array<mixed> */
    public function toArray(): array
    {
        return $this->document;
    }

    /** @return array<mixed> */
    public function jsonSerialize(): array
    {
        return $this->document;
    }

    public function plainText(): string
    {
        return Str::of(self::nodeText(self::stringKeyedNode($this->document)))->squish()->toString();
    }

    public function readingTime(): int
    {
        $text = $this->plainText();

        if ($text === '') {
            return 1;
        }

        preg_match_all("/[\\p{L}\\p{N}]+(?:['’_-][\\p{L}\\p{N}]+)*/u", $text, $matches);
        $wordCount = count($matches[0]);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    /** @return list<int> */
    public function referencedMediaIds(): array
    {
        $mediaIds = [];
        self::walk(self::stringKeyedNode($this->document), function (array $node) use (&$mediaIds): void {
            if (($node['type'] ?? null) !== 'image') {
                return;
            }

            $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $mediaId = $attributes['mediaId'] ?? null;

            if (is_int($mediaId) && $mediaId > 0) {
                $mediaIds[] = $mediaId;
            }
        });

        return array_values(array_unique($mediaIds));
    }

    /**
     * Prepare the document for the public renderer. Stored image sources are
     * ignored; only server-resolved post media may reach the browser.
     *
     * @param  array<int, array{url: string, width: int, height: int}>  $media
     * @return array{document: array<string, mixed>|null, table_of_contents: list<array{id: string, title: string, level: int}>}
     */
    public function readerDocument(array $media = []): array
    {
        $headingCounts = [];
        $tableOfContents = [];
        $prepared = self::prepareNode(
            self::stringKeyedNode($this->document),
            $media,
            $headingCounts,
            $tableOfContents,
        );

        return [
            'document' => $prepared,
            'table_of_contents' => $tableOfContents,
        ];
    }

    public static function supportsCodeLanguage(mixed $language): bool
    {
        return is_string($language) && in_array($language, self::CODE_LANGUAGES, true);
    }

    /** @param array<string, mixed> $node */
    private static function nodeText(array $node): string
    {
        $text = is_string($node['text'] ?? null) ? $node['text'] : '';
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return $text;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                $text .= ' '.self::nodeText(self::stringKeyedNode($child));
            }
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(array<string, mixed>): void  $callback
     */
    private static function walk(array $node, callable $callback): void
    {
        $callback($node);
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return;
        }

        foreach ($content as $child) {
            if (is_array($child)) {
                self::walk(self::stringKeyedNode($child), $callback);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, array{url: string, width: int, height: int}>  $media
     * @param  array<string, int>  $headingCounts
     * @param  list<array{id: string, title: string, level: int}>  $tableOfContents
     * @return array<string, mixed>|null
     */
    private static function prepareNode(
        array $node,
        array $media,
        array &$headingCounts,
        array &$tableOfContents,
    ): ?array {
        $type = $node['type'] ?? null;

        if ($type === 'heading') {
            $title = Str::of(self::nodeText($node))->squish()->toString();
            $baseId = Str::slug($title);
            $baseId = $baseId === '' ? 'section' : $baseId;
            $headingCounts[$baseId] = ($headingCounts[$baseId] ?? 0) + 1;
            $id = $headingCounts[$baseId] === 1 ? $baseId : $baseId.'-'.$headingCounts[$baseId];
            $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $level = is_int($attributes['level'] ?? null) ? $attributes['level'] : 2;
            $node['attrs'] = ['level' => $level, 'id' => $id];
            $tableOfContents[] = ['id' => $id, 'title' => $title, 'level' => $level];
        }

        if ($type === 'image') {
            $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $mediaId = $attributes['mediaId'] ?? null;

            if (! is_int($mediaId) || ! isset($media[$mediaId])) {
                return null;
            }

            $node['attrs'] = [
                'mediaId' => $mediaId,
                'src' => $media[$mediaId]['url'],
                'alt' => is_string($attributes['alt'] ?? null) ? $attributes['alt'] : '',
                'caption' => is_string($attributes['caption'] ?? null) ? $attributes['caption'] : null,
                'width' => $media[$mediaId]['width'],
                'height' => $media[$mediaId]['height'],
            ];
        }

        $content = $node['content'] ?? null;

        if (is_array($content)) {
            $preparedContent = [];

            foreach ($content as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $preparedChild = self::prepareNode(
                    self::stringKeyedNode($child),
                    $media,
                    $headingCounts,
                    $tableOfContents,
                );

                if ($preparedChild !== null) {
                    $preparedContent[] = $preparedChild;
                }
            }

            $node['content'] = $preparedContent;
        }

        return $node;
    }

    /**
     * JSON objects are string-keyed, but validation boundaries initially
     * expose generic PHP arrays. Ignore numeric keys before traversing a node.
     *
     * @param  array<mixed>  $node
     * @return array<string, mixed>
     */
    private static function stringKeyedNode(array $node): array
    {
        $normalized = [];

        foreach ($node as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
