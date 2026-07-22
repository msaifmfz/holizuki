<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Markdown;

use Illuminate\Support\Str;

/**
 * Shared vocabulary for the markdown rendition of a RichTextDocument. The
 * dialect is CommonMark plus strikethrough, `<u>` inline HTML for underline,
 * and a `media:{id}` URL scheme so image nodes keep pointing at PostMedia
 * rows across a serialize/parse round trip.
 */
final class MarkdownDialect
{
    public const string MEDIA_SCHEME = 'media:';

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

        $identifier = substr($url, strlen(self::MEDIA_SCHEME));

        return Str::isMatch('/\A\d+\z/', $identifier) && (int) $identifier > 0 ? (int) $identifier : null;
    }
}
