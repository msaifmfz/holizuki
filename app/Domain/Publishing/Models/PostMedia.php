<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Enums\PostMediaPlacement;
use App\Domain\Publishing\Enums\PostMediaRole;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use Carbon\CarbonInterface;
use Database\Factories\PostMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property string $path
 * @property int $width
 * @property int $height
 * @property string|null $display_name
 * @property string|null $alt_text
 * @property string|null $caption
 * @property PostMediaRole $role
 * @property PostMediaPlacement $placement
 * @property PostMediaOrigin $origin
 * @property PostMediaStatus $status
 * @property PostMediaStoryPriority $story_priority
 * @property string|null $story_notes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'path', 'width', 'height', 'display_name', 'alt_text', 'caption',
    'role', 'placement', 'origin', 'status',
    'story_priority', 'story_notes',
])]
class PostMedia extends Model
{
    /** @use HasFactory<PostMediaFactory> */
    use HasFactory;

    /**
     * How many images one post may hold, across every upload route. The
     * assistant workspace budgets for this plus the featured image, so raising
     * it means raising `assistant.limits.image_count` too.
     */
    public const int MAX_PER_POST = 20;

    public const string MAX_PER_POST_MESSAGE = 'A post may have at most 20 uploaded photos.';

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'role' => PostMediaRole::Unassigned->value,
        'placement' => PostMediaPlacement::Unassigned->value,
        'origin' => PostMediaOrigin::InlineEditor->value,
        'status' => PostMediaStatus::Ready->value,
        'story_priority' => PostMediaStoryPriority::Optional->value,
    ];

    protected static function newFactory(): PostMediaFactory
    {
        return PostMediaFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'role' => PostMediaRole::class,
            'placement' => PostMediaPlacement::class,
            'origin' => PostMediaOrigin::class,
            'status' => PostMediaStatus::class,
            'story_priority' => PostMediaStoryPriority::class,
        ];
    }
}
