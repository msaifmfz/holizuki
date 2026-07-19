<?php

declare(strict_types=1);

namespace App\Domain\Reading\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Support\Facades\Storage;

class Seo
{
    public const string DEFAULT_DESCRIPTION = 'Travel stories, itineraries, and practical guides from the road.';

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
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $prevUrl = null,
        ?string $nextUrl = null,
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
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'prev_url' => self::withoutFirstPageParameter($prevUrl),
            'next_url' => $nextUrl,
            'json_ld' => $jsonLd,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * The SEO payload for a public post page, applying per-post overrides
     * with fallbacks to the post's reader-facing fields.
     *
     * @param  array<string, mixed>|null  $jsonLd
     * @return array<string, mixed>
     */
    public static function forPost(Post $post, ?string $featuredImageUrl, ?array $jsonLd = null): array
    {
        $seo = $post->seoMetadata();

        return self::make(
            title: $seo->title ?? (string) $post->title,
            description: $seo->description ?? $post->excerpt,
            canonical: $seo->canonicalUrl ?? route('public.posts.show', $post->slug),
            image: self::postOgImageUrl($post) ?? $featuredImageUrl,
            type: 'article',
            publishedTime: $post->published_at?->toISOString(),
            modifiedTime: ($post->content_updated_at ?? $post->published_at)?->toISOString(),
            author: $post->author?->name,
            robots: $seo->noindex ? 'noindex, follow' : null,
            ogTitle: $seo->ogTitle ?? $seo->title,
            ogDescription: $seo->ogDescription ?? $seo->description,
            jsonLd: $jsonLd,
        );
    }

    public static function postOgImageUrl(Post $post): ?string
    {
        $ogImagePath = $post->seoMetadata()->ogImagePath;

        return $ogImagePath === null ? null : Storage::disk('public')->url($ogImagePath);
    }

    /**
     * Page 1 is canonically the bare URL, so rel=prev links pointing at it
     * must not carry a page=1 query parameter.
     */
    private static function withoutFirstPageParameter(?string $url): ?string
    {
        if ($url === null || ! str_contains($url, 'page=1')) {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str(is_string($query) ? $query : '', $parameters);

        if (($parameters['page'] ?? null) !== '1') {
            return $url;
        }

        unset($parameters['page']);
        [$base] = explode('?', $url, 2);

        return $parameters === [] ? $base : $base.'?'.http_build_query($parameters);
    }

    /**
     * The schema.org graph for a public post page: the Article, its author's
     * Person node (referenced by @id as both author and publisher), and the
     * breadcrumb trail.
     *
     * @return array<string, mixed>
     */
    public static function articleGraph(Post $post, ?string $featuredImageUrl): array
    {
        $person = $post->author === null ? null : self::personJsonLd($post->author);
        $personReference = $person === null ? null : ['@id' => $person['@id']];

        $article = array_filter([
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->seoMetadata()->description ?? $post->excerpt,
            'image' => self::postOgImageUrl($post) ?? $featuredImageUrl,
            'datePublished' => $post->published_at?->toISOString(),
            'dateModified' => ($post->content_updated_at ?? $post->published_at)?->toISOString(),
            'mainEntityOfPage' => route('public.posts.show', $post->slug),
            'timeRequired' => 'PT'.($post->reading_time_minutes ?? 1).'M',
            'author' => $personReference,
            'publisher' => $personReference,
        ], static fn (mixed $value): bool => $value !== null);

        $breadcrumbs = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Home',
                'item' => route('home'),
            ],
        ];

        if ($post->category !== null) {
            $breadcrumbs[] = [
                '@type' => 'ListItem',
                'position' => count($breadcrumbs) + 1,
                'name' => $post->category->name,
                'item' => route('public.categories.show', $post->category->slug),
            ];
        }

        $breadcrumbs[] = [
            '@type' => 'ListItem',
            'position' => count($breadcrumbs) + 1,
            'name' => $post->title,
            'item' => route('public.posts.show', $post->slug),
        ];

        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter([
                $article,
                $person,
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbs,
                ],
            ], static fn (?array $node): bool => $node !== null)),
        ];
    }

    /**
     * The site owner's Person node, shared by author and publisher references
     * in the article graph via its @id.
     *
     * @return array<string, mixed>
     */
    public static function personJsonLd(User $user): array
    {
        $profileUrl = $user->author_slug === null
            ? route('home')
            : route('public.authors.show', $user->author_slug);
        $socialLinks = array_values(array_filter(
            $user->social_links ?? [],
            static fn (string $link): bool => $link !== '',
        ));

        return array_filter([
            '@type' => 'Person',
            '@id' => $profileUrl.'#person',
            'name' => $user->name,
            'url' => $profileUrl,
            'sameAs' => $socialLinks === [] ? null : $socialLinks,
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
