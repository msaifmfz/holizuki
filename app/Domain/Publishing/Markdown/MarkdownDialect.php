<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Markdown;

use Illuminate\Support\Str;

/**
 * Shared vocabulary for the markdown rendition of a RichTextDocument. The
 * dialect is CommonMark plus strikethrough, `<u>` inline HTML for underline,
 * a `media:{id}` URL scheme so image nodes keep pointing at PostMedia rows,
 * and an empty link with a `source:{id}` destination for citation references
 * across a serialize/parse round trip.
 */
final class MarkdownDialect
{
    public const string MEDIA_SCHEME = 'media:';

    public const string SOURCE_SCHEME = 'source:';

    public static function citation(int $sourceId): string
    {
        return '[]('.self::SOURCE_SCHEME.$sourceId.')';
    }

    public static function sourceIdFromCitationUrl(string $url): ?int
    {
        if (! str_starts_with($url, self::SOURCE_SCHEME)) {
            return null;
        }

        return self::positiveInteger(substr($url, strlen(self::SOURCE_SCHEME)));
    }

    /**
     * Canonical outermost-to-innermost nesting order for marks. Serialization
     * and parsing both emit marks sorted by this priority so a round trip is
     * byte-stable regardless of the order the editor stored them in.
     *
     * @var array<string, int>
     */
    public const array MARK_PRIORITY = [
        'link' => 0,
        'bold' => 1,
        'italic' => 2,
        'strike' => 3,
        'underline' => 4,
        'code' => 5,
    ];

    private function __construct() {}

    /**
     * @param  list<array<mixed>>  $marks
     * @return list<array<mixed>>
     */
    public static function sortMarks(array $marks): array
    {
        $priority = function (array $mark): int {
            $type = $mark['type'] ?? null;

            return is_string($type) ? (self::MARK_PRIORITY[$type] ?? 99) : 99;
        };

        usort($marks, fn (array $left, array $right): int => $priority($left) <=> $priority($right));

        return $marks;
    }

    public static function mediaUrl(int $mediaId): string
    {
        return self::MEDIA_SCHEME.$mediaId;
    }

    public static function mediaIdFromUrl(string $url): ?int
    {
        if (! str_starts_with($url, self::MEDIA_SCHEME)) {
            return null;
        }

        return self::positiveInteger(substr($url, strlen(self::MEDIA_SCHEME)));
    }

    private static function positiveInteger(string $identifier): ?int
    {
        if (! Str::isMatch('/\A[1-9]\d*\z/', $identifier)) {
            return null;
        }

        $maximum = (string) PHP_INT_MAX;

        if (strlen($identifier) > strlen($maximum)
            || (strlen($identifier) === strlen($maximum) && strcmp($identifier, $maximum) > 0)) {
            return null;
        }

        return (int) $identifier;
    }
}
