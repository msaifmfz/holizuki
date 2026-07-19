<?php

declare(strict_types=1);

namespace App\Domain\Analytics\ValueObjects;

/**
 * The single source of truth for the additive metric columns shared by the
 * sync payload preparation, batched GA reports, and persistence layers.
 */
final class AnalyticsMetricColumns
{
    /** @var list<string> */
    public const array NAMES = [
        'readers', 'meaningful_readers', 'actioning_readers', 'sessions', 'page_views',
        'article_progress_25', 'article_progress_50', 'article_progress_75',
        'article_progress_90', 'article_engaged', 'select_content', 'shares', 'sign_ups',
        'comment_submits', 'outbound_clicks', 'file_downloads',
    ];

    private function __construct() {}

    /** @return array<string, int> */
    public static function zeroed(): array
    {
        return array_fill_keys(self::NAMES, 0);
    }
}
