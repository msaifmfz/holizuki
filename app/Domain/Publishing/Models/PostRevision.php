<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Casts\PostRevisionSnapshotCast;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\ValueObjects\PostRevisionSnapshot;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use Carbon\CarbonInterface;
use Database\Factories\PostRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $editor_id
 * @property int $revision_number
 * @property PostRevisionEvent $event
 * @property PostRevisionSnapshot $snapshot
 * @property list<string> $referenced_paths
 * @property list<int> $referenced_media_ids
 * @property string|null $title
 * @property string $slug
 * @property string|null $excerpt
 * @property RichTextDocument|null $body
 * @property string|null $featured_image_path
 * @property string|null $featured_image_alt
 * @property string|null $featured_image_caption
 * @property string|null $seo_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property string|null $og_title
 * @property string|null $og_description
 * @property string|null $og_image_path
 * @property bool $noindex
 * @property CarbonInterface $created_at
 */
#[Fillable([
    'post_id', 'editor_id', 'revision_number', 'event', 'snapshot',
])]
class PostRevision extends Model
{
    /** @use HasFactory<PostRevisionFactory> */
    use HasFactory;

    protected static function newFactory(): PostRevisionFactory
    {
        return PostRevisionFactory::new();
    }

    public const UPDATED_AT = null;

    /**
     * @param  Builder<PostRevision>  $query
     */
    protected function scopeReferencingPath(Builder $query, string $path): void
    {
        $query->whereJsonContains('referenced_paths', $path);
    }

    /**
     * @param  Builder<PostRevision>  $query
     */
    protected function scopeReferencingMedia(Builder $query, int $mediaId): void
    {
        $query->whereJsonContains('referenced_media_ids', $mediaId);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    /** @return Attribute<string|null, never> */
    protected function title(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->title);
    }

    /** @return Attribute<string, never> */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => $this->snapshot->slug);
    }

    /** @return Attribute<string|null, never> */
    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->excerpt);
    }

    /** @return Attribute<RichTextDocument|null, never> */
    protected function body(): Attribute
    {
        return Attribute::get(fn (): ?RichTextDocument => $this->snapshot->body);
    }

    /** @return Attribute<string|null, never> */
    protected function featuredImagePath(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->featuredImagePath);
    }

    /** @return Attribute<string|null, never> */
    protected function featuredImageAlt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->featuredImageAlt);
    }

    /** @return Attribute<string|null, never> */
    protected function featuredImageCaption(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->featuredImageCaption);
    }

    /** @return Attribute<string|null, never> */
    protected function seoTitle(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->seoTitle);
    }

    /** @return Attribute<string|null, never> */
    protected function metaDescription(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->metaDescription);
    }

    /** @return Attribute<string|null, never> */
    protected function canonicalUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->canonicalUrl);
    }

    /** @return Attribute<string|null, never> */
    protected function ogTitle(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->ogTitle);
    }

    /** @return Attribute<string|null, never> */
    protected function ogDescription(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->ogDescription);
    }

    /** @return Attribute<string|null, never> */
    protected function ogImagePath(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->snapshot->ogImagePath);
    }

    /** @return Attribute<bool, never> */
    protected function noindex(): Attribute
    {
        return Attribute::get(fn (): bool => $this->snapshot->noindex);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'snapshot' => PostRevisionSnapshotCast::class,
            'referenced_paths' => 'array',
            'referenced_media_ids' => 'array',
            'event' => PostRevisionEvent::class,
            'revision_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
