<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AuthoringArtifactType;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AuthoringArtifactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Override;

/**
 * @property int $id
 * @property string $public_id
 * @property int $post_id
 * @property int $assistant_authoring_task_id
 * @property int|null $parent_id
 * @property AuthoringArtifactType $type
 * @property string $group_id
 * @property int|null $position
 * @property int $base_lock_version
 * @property array<string, mixed> $payload
 * @property CarbonInterface|null $created_at
 */
#[Fillable([
    'public_id', 'post_id', 'assistant_authoring_task_id', 'parent_id', 'type',
    'group_id', 'position', 'base_lock_version', 'payload',
])]
#[Table(name: 'assistant_authoring_artifacts')]
final class AuthoringArtifact extends Model
{
    /** @use HasFactory<AuthoringArtifactFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function newFactory(): AuthoringArtifactFactory
    {
        return AuthoringArtifactFactory::new();
    }

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    #[Override]
    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException('Authoring artifact payloads are immutable.');
        });
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return BelongsTo<AuthoringTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AuthoringTask::class, 'assistant_authoring_task_id');
    }

    /** @return BelongsTo<AuthoringArtifact, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<AuthoringArtifact, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isStaleFor(Post $post): bool
    {
        return $this->base_lock_version !== $post->lock_version;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => AuthoringArtifactType::class,
            'position' => 'integer',
            'base_lock_version' => 'integer',
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
