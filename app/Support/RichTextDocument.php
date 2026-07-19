<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

class RichTextDocument
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

    /** @param array<mixed>|null $document */
    public static function plainText(?array $document): string
    {
        if ($document === null) {
            return '';
        }

        return Str::of(self::nodeText(self::stringKeyedNode($document)))->squish()->toString();
    }

    /** @param array<mixed>|null $document */
    public static function readingTime(?array $document): int
    {
        $text = self::plainText($document);

        if ($text === '') {
            return 1;
        }

        preg_match_all("/[\\p{L}\\p{N}]+(?:['’_-][\\p{L}\\p{N}]+)*/u", $text, $matches);
        $wordCount = count($matches[0]);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    /**
     * @param  array<mixed>|null  $document
     * @return list<int>
     */
    public static function referencedMediaIds(?array $document): array
    {
        if ($document === null) {
            return [];
        }

        $mediaIds = [];
        self::walk(self::stringKeyedNode($document), function (array $node) use (&$mediaIds): void {
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
     * Prepare a document for the public renderer. Stored image sources are
     * ignored; only server-resolved post media may reach the browser.
     *
     * @param  array<mixed>|null  $document
     * @param  array<int, array{url: string, width: int, height: int}>  $media
     * @return array{document: array<string, mixed>|null, table_of_contents: list<array{id: string, title: string, level: int}>}
     */
    public static function readerDocument(?array $document, array $media = []): array
    {
        if ($document === null) {
            return ['document' => null, 'table_of_contents' => []];
        }

        $headingCounts = [];
        $tableOfContents = [];
        $prepared = self::prepareNode(
            self::stringKeyedNode($document),
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
