<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonInterface;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $id
 * @property int|null $author_id
 * @property int|null $updated_by_id
 * @property int|null $category_id
 * @property string|null $title
 * @property string $slug
 * @property bool $slug_is_manual
 * @property string|null $excerpt
 * @property array<string, mixed>|null $body
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
 * @property CarbonInterface|null $content_updated_at
 * @property CarbonInterface|null $featured_at
 * @property int|null $reading_time_minutes
 * @property string|null $search_text
 * @property PostStatus $status
 * @property CarbonInterface|null $scheduled_at
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $slug_locked_at
 * @property int $lock_version
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable([
    'author_id', 'updated_by_id', 'category_id', 'title', 'slug', 'slug_is_manual', 'excerpt', 'body',
    'featured_image_path', 'featured_image_alt', 'featured_image_caption', 'featured_at',
    'seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description', 'og_image_path',
    'noindex', 'content_updated_at',
    'reading_time_minutes', 'search_text', 'status', 'scheduled_at', 'published_at', 'slug_locked_at',
    'lock_version',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Reader-visible fields; changing any of them refreshes content_updated_at,
     * the freshness signal used for sitemap lastmod and article dateModified.
     *
     * @var list<string>
     */
    public const array CONTENT_FIELDS = [
        'title', 'slug', 'excerpt', 'body', 'featured_image_path',
        'featured_image_alt', 'featured_image_caption', 'category_id',
    ];

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => PostStatus::Draft->value,
        'slug_is_manual' => false,
        'noindex' => false,
        'lock_version' => 0,
    ];

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
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

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class);
    }

    /** @return HasMany<PostView, $this> */
    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    /** @return HasMany<PostMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    /** @param Builder<Post> $query */
    protected function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published);
    }

    /** @param Builder<Post> $query */
    protected function scopeScheduled(Builder $query): void
    {
        $query->where('status', PostStatus::Draft)->whereNotNull('scheduled_at');
    }

    /** @param Builder<Post> $query */
    protected function scopeSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $builder) use ($pattern): void {
            $builder->whereLike('title', $pattern)->orWhereLike('slug', $pattern);
        });
    }

    /** @param Builder<Post> $query */
    protected function scopePublicSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $pattern = '%'.addcslashes($term, '%_\\').'%';

        if ($query->getModel()->getConnection()->getDriverName() === 'pgsql') {
            $query
                ->where(function (Builder $builder) use ($term, $pattern): void {
                    $builder
                        ->whereFullText('search_text', $term, ['language' => 'english'])
                        ->orWhereIn('category_id', Category::query()->whereLike('name', $pattern)->select('id'))
                        ->orWhereIn('author_id', User::query()->whereLike('name', $pattern)->select('id'))
                        ->orWhereHas('tags', fn (Builder $tagQuery): Builder => $tagQuery->whereLike('name', $pattern));
                })
                ->selectRaw(
                    "ts_rank(to_tsvector('english', coalesce(search_text, '')), plainto_tsquery('english', ?)) as search_rank",
                    [$term],
                )
                ->reorder()
                ->orderByDesc('search_rank')
                ->latest('published_at')
                ->orderByDesc('id');

            return;
        }

        $query
            ->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereLike('search_text', $pattern)
                    ->orWhereLike('title', $pattern)
                    ->orWhereLike('excerpt', $pattern)
                    ->orWhereLike('body', $pattern)
                    ->orWhereIn('category_id', Category::query()->whereLike('name', $pattern)->select('id'))
                    ->orWhereIn('author_id', User::query()->whereLike('name', $pattern)->select('id'))
                    ->orWhereHas('tags', fn (Builder $tagQuery): Builder => $tagQuery->whereLike('name', $pattern));
            })
            ->selectRaw(
                'case when title like ? then 5 when excerpt like ? then 4 when body like ? then 3 else 1 end as search_rank',
                [$pattern, $pattern, $pattern],
            )
            ->reorder()
            ->orderByDesc('search_rank')
            ->latest('published_at')
            ->orderByDesc('id');
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Draft && $this->scheduled_at !== null;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'body' => 'array',
            'slug_is_manual' => 'boolean',
            'noindex' => 'boolean',
            'content_updated_at' => 'datetime',
            'featured_at' => 'datetime',
            'reading_time_minutes' => 'integer',
            'status' => PostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'slug_locked_at' => 'datetime',
            'lock_version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
