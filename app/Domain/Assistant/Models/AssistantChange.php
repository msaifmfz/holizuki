<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AssistantChangeStatus;
use App\Domain\Assistant\Enums\AssistantChangeType;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AssistantChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * A single reviewable proposal produced by an agent turn. Scalar changes
 * carry {old, new} values; body changes carry markdown hunks with anchors.
 * Nothing touches the post until the author accepts.
 *
 * @property int $id
 * @property int $assistant_turn_id
 * @property int $post_id
 * @property AssistantChangeType $type
 * @property AssistantChangeStatus $status
 * @property array<string, mixed> $payload
 * @property int $base_lock_version
 * @property CarbonInterface|null $decided_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['assistant_turn_id', 'post_id', 'type', 'status', 'payload', 'base_lock_version', 'decided_at'])]
class AssistantChange extends Model
{
    /** @use HasFactory<AssistantChangeFactory> */
    use HasFactory;

    protected static function newFactory(): AssistantChangeFactory
    {
        return AssistantChangeFactory::new();
    }

    /** @return BelongsTo<AssistantTurn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AssistantTurn::class, 'assistant_turn_id');
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopePending(Builder $query): Builder
    {
        return $query->where('status', AssistantChangeStatus::Proposed);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => AssistantChangeType::class,
            'status' => AssistantChangeStatus::class,
            'payload' => 'array',
            'base_lock_version' => 'integer',
            'decided_at' => 'datetime',
        ];
    }
}
