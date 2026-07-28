<?php

declare(strict_types=1);

namespace App\Domain\Publishing\ValueObjects;

use App\Domain\Publishing\Models\PostDraft;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;

/**
 * The complete editable state captured by an immutable post revision.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class PostRevisionSnapshot implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?int $authorId,
        public ?int $categoryId,
        public ?string $title,
        public string $slug,
        public bool $slugIsManual,
        public ?string $excerpt,
        public ?RichTextDocument $body,
        public ?string $featuredImagePath,
        public ?string $featuredImageAlt,
        public ?string $featuredImageCaption,
        public ?string $seoTitle,
        public ?string $metaDescription,
        public ?string $canonicalUrl,
        public ?string $ogTitle,
        public ?string $ogDescription,
        public ?string $ogImagePath,
        public bool $noindex,
        public array $tags,
    ) {}

    public static function fromDraft(PostDraft $draft): self
    {
        $draft->loadMissing('tags:id,name');

        return new self(
            authorId: $draft->author_id,
            categoryId: $draft->category_id,
            title: $draft->title,
            slug: $draft->slug,
            slugIsManual: $draft->slug_is_manual,
            excerpt: $draft->excerpt,
            body: $draft->body,
            featuredImagePath: $draft->featured_image_path,
            featuredImageAlt: $draft->featured_image_alt,
            featuredImageCaption: $draft->featured_image_caption,
            seoTitle: $draft->seo_title,
            metaDescription: $draft->meta_description,
            canonicalUrl: $draft->canonical_url,
            ogTitle: $draft->og_title,
            ogDescription: $draft->og_description,
            ogImagePath: $draft->og_image_path,
            noindex: $draft->noindex,
            tags: array_values($draft->tags
                ->map(fn (Tag $tag): string => $tag->name)
                ->all()),
        );
    }

    /** @param array<mixed> $snapshot */
    public static function fromArray(array $snapshot): self
    {
        $slug = $snapshot['slug'] ?? null;

        if (! is_string($slug) || $slug === '') {
            throw new InvalidArgumentException('A post revision snapshot must contain a slug.');
        }

        $body = $snapshot['body'] ?? null;

        return new self(
            authorId: self::nullableInteger($snapshot['author_id'] ?? null),
            categoryId: self::nullableInteger($snapshot['category_id'] ?? null),
            title: self::nullableString($snapshot['title'] ?? null),
            slug: $slug,
            slugIsManual: ($snapshot['slug_is_manual'] ?? false) === true,
            excerpt: self::nullableString($snapshot['excerpt'] ?? null),
            body: is_array($body) ? RichTextDocument::fromArray($body) : null,
            featuredImagePath: self::nullableString($snapshot['featured_image_path'] ?? null),
            featuredImageAlt: self::nullableString($snapshot['featured_image_alt'] ?? null),
            featuredImageCaption: self::nullableString($snapshot['featured_image_caption'] ?? null),
            seoTitle: self::nullableString($snapshot['seo_title'] ?? null),
            metaDescription: self::nullableString($snapshot['meta_description'] ?? null),
            canonicalUrl: self::nullableString($snapshot['canonical_url'] ?? null),
            ogTitle: self::nullableString($snapshot['og_title'] ?? null),
            ogDescription: self::nullableString($snapshot['og_description'] ?? null),
            ogImagePath: self::nullableString($snapshot['og_image_path'] ?? null),
            noindex: ($snapshot['noindex'] ?? false) === true,
            tags: self::tagNames($snapshot['tags'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'author_id' => $this->authorId,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'slug' => $this->slug,
            'slug_is_manual' => $this->slugIsManual,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'featured_image_path' => $this->featuredImagePath,
            'featured_image_alt' => $this->featuredImageAlt,
            'featured_image_caption' => $this->featuredImageCaption,
            'seo_title' => $this->seoTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'og_title' => $this->ogTitle,
            'og_description' => $this->ogDescription,
            'og_image_path' => $this->ogImagePath,
            'noindex' => $this->noindex,
        ];
    }

    /** @return list<string> */
    public function referencedFilePaths(): array
    {
        return array_values(array_unique(array_filter([
            $this->featuredImagePath,
            $this->ogImagePath,
        ], is_string(...))));
    }

    /** @return list<int> */
    public function referencedMediaIds(): array
    {
        return $this->body?->referencedMediaIds() ?? [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => 1,
            ...$this->attributes(),
            'body' => $this->body?->toArray(),
            'tags' => $this->tags,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function nullableInteger(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    private static function tagNames(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $normalized = [];

        foreach ($tags as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $name = trim($tag);

            if ($name !== '') {
                $normalized[Str::lower($name)] = $name;
            }
        }

        return array_values($normalized);
    }
}
