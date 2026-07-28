<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Casts\RichTextDocumentCast;
use App\Domain\Publishing\ValueObjects\PostRevisionSnapshot;
use App\Domain\Publishing\ValueObjects\RichTextDocument;
use App\Domain\Publishing\ValueObjects\SeoMetadata;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use Carbon\CarbonInterface;
use Database\Factories\PostDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $author_id
 * @property int|null $category_id
 * @property string|null $title
 * @property string $slug
 * @property bool $slug_is_manual
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
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'author_id', 'category_id', 'title', 'slug', 'slug_is_manual', 'excerpt', 'body',
    'featured_image_path', 'featured_image_alt', 'featured_image_caption',
    'seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
    'og_image_path', 'noindex',
])]
final class PostDraft extends Model
{
    /** @use HasFactory<PostDraftFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'slug_is_manual' => false,
        'noindex' => false,
    ];

    protected static function newFactory(): PostDraftFactory
    {
        return PostDraftFactory::new();
    }

    /** @return array<string, mixed> */
    public static function initialAttributes(Post $post): array
    {
        return [
            'author_id' => $post->author_id,
            'category_id' => $post->category_id,
            'title' => $post->title,
            'slug' => $post->slug,
            'slug_is_manual' => $post->slug_is_manual,
            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'featured_image_path' => $post->featured_image_path,
            'featured_image_alt' => $post->featured_image_alt,
            'featured_image_caption' => $post->featured_image_caption,
            'seo_title' => $post->seo_title,
            'meta_description' => $post->meta_description,
            'canonical_url' => $post->canonical_url,
            'og_title' => $post->og_title,
            'og_description' => $post->og_description,
            'og_image_path' => $post->og_image_path,
            'noindex' => $post->noindex,
        ];
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function snapshot(): PostRevisionSnapshot
    {
        return PostRevisionSnapshot::fromDraft($this);
    }

    public function seoMetadata(): SeoMetadata
    {
        return new SeoMetadata(
            title: $this->seo_title,
            description: $this->meta_description,
            canonicalUrl: $this->canonical_url,
            ogTitle: $this->og_title,
            ogDescription: $this->og_description,
            ogImagePath: $this->og_image_path,
            noindex: $this->noindex,
        );
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'body' => RichTextDocumentCast::class,
            'slug_is_manual' => 'boolean',
            'noindex' => 'boolean',
        ];
    }
}
