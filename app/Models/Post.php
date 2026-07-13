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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $id
 * @property int|null $author_id
 * @property int|null $updated_by_id
 * @property string|null $title
 * @property string $slug
 * @property bool $slug_is_manual
 * @property string|null $excerpt
 * @property array<string, mixed>|null $body
 * @property string|null $featured_image_path
 * @property string|null $featured_image_alt
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
    'author_id', 'updated_by_id', 'title', 'slug', 'slug_is_manual', 'excerpt', 'body',
    'featured_image_path', 'featured_image_alt', 'status', 'scheduled_at', 'published_at',
    'slug_locked_at', 'lock_version',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => PostStatus::Draft->value,
        'slug_is_manual' => false,
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

    /** @return HasMany<PostRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class);
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
            'status' => PostStatus::class,
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'slug_locked_at' => 'datetime',
            'lock_version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
