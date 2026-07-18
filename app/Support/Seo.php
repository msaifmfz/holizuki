<?php

declare(strict_types=1);

namespace App\Support;

class Seo
{
    public const string DEFAULT_DESCRIPTION = 'Writing on software, product, and the craft of building for the web.';

    public static function siteName(): string
    {
        return config()->string('app.name', 'Holizuki');
    }

    /**
     * Build the SEO payload rendered as meta tags in the root Blade view.
     *
     * @param  array<string, mixed>|null  $jsonLd
     * @return array<string, mixed>
     */
    public static function make(
        string $title,
        ?string $description = null,
        ?string $canonical = null,
        ?string $image = null,
        string $type = 'website',
        ?string $publishedTime = null,
        ?string $modifiedTime = null,
        ?string $author = null,
        ?string $robots = null,
        ?array $jsonLd = null,
    ): array {
        return array_filter([
            'title' => $title,
            'description' => $description ?? self::DEFAULT_DESCRIPTION,
            'canonical' => $canonical,
            'image' => $image,
            'type' => $type,
            'published_time' => $publishedTime,
            'modified_time' => $modifiedTime,
            'author' => $author,
            'robots' => $robots,
            'json_ld' => $jsonLd,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The WebSite schema with a SearchAction, used on the homepage.
     *
     * @return array<string, mixed>
     */
    public static function websiteJsonLd(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => self::siteName(),
            'url' => route('home'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('public.search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
}
