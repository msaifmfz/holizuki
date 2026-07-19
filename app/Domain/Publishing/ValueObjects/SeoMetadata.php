<?php

declare(strict_types=1);

namespace App\Domain\Publishing\ValueObjects;

/**
 * The per-post SEO overrides as one read-side composite. Null fields mean
 * "no override"; consumers decide the fallback (post title, excerpt, ...).
 */
final readonly class SeoMetadata
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?string $ogImagePath,
        public bool $noindex,
    ) {}
}
