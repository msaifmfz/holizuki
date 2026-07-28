<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Enums\PostSourceVisibility;
use Carbon\CarbonInterface;
use Database\Factories\PostSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $created_by_id
 * @property PostSourceKind $kind
 * @property string $title
 * @property string|null $url
 * @property string|null $content
 * @property string|null $path
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property PostSourceVisibility $visibility
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'created_by_id', 'kind', 'title', 'url', 'content', 'path',
    'original_name', 'mime_type', 'size_bytes', 'visibility', 'approved_at',
])]
final class PostSource extends Model
{
    /** @use HasFactory<PostSourceFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'visibility' => PostSourceVisibility::Private->value,
    ];

    protected static function newFactory(): PostSourceFactory
    {
        return PostSourceFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => PostSourceKind::class,
            'visibility' => PostSourceVisibility::class,
            'approved_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }
}
