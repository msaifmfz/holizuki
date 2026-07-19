<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostRevisionEvent;
use Carbon\CarbonInterface;
use Database\Factories\PostRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property string|null $title
 * @property string $slug
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
 * @property CarbonInterface $created_at
 */
#[Fillable([
    'post_id', 'editor_id', 'revision_number', 'event', 'title', 'slug', 'excerpt',
    'body', 'featured_image_path', 'featured_image_alt', 'featured_image_caption',
    'seo_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
    'og_image_path', 'noindex',
])]
class PostRevision extends Model
{
    /** @use HasFactory<PostRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

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

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'body' => 'array',
            'event' => PostRevisionEvent::class,
            'revision_number' => 'integer',
            'noindex' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
