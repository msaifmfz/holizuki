<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Enums;

enum AssistantChangeType: string
{
    case Body = 'body';
    case Title = 'title';
    case Excerpt = 'excerpt';
    case SeoTitle = 'seo_title';
    case MetaDescription = 'meta_description';
    case OgTitle = 'og_title';
    case OgDescription = 'og_description';
    case Tags = 'tags';
    case FeaturedImageAlt = 'featured_image_alt';
    case FeaturedImageCaption = 'featured_image_caption';

    /**
     * The meta.json key (and Post attribute) a scalar change maps onto.
     * Body changes are hunk-based and resolve through the markdown codec.
     */
    public function postAttribute(): ?string
    {
        return $this === self::Body ? null : $this->value;
    }

    public static function fromMetaKey(string $key): ?self
    {
        return match ($key) {
            'title' => self::Title,
            'excerpt' => self::Excerpt,
            'seo_title' => self::SeoTitle,
            'meta_description' => self::MetaDescription,
            'og_title' => self::OgTitle,
            'og_description' => self::OgDescription,
            'tags' => self::Tags,
            'featured_image_alt' => self::FeaturedImageAlt,
            'featured_image_caption' => self::FeaturedImageCaption,
            default => null,
        };
    }
}
