<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use Carbon\CarbonInterface;
use Database\Factories\AssistantTurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * One agent invocation. Carries the pre-turn workspace snapshot the diff
 * runs against, so proposals are computed even if the workspace is later
 * re-materialized.
 *
 * @property int $id
 * @property int $assistant_session_id
 * @property AssistantTaskType $task_type
 * @property AssistantTurnStatus $status
 * @property string $user_prompt
 * @property string|null $assistant_message
 * @property array<string, mixed>|null $context
 * @property string|null $snapshot_draft
 * @property array<string, mixed>|null $snapshot_meta
 * @property numeric-string|null $cost_usd
 * @property int|null $duration_ms
 * @property string|null $error
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'assistant_session_id', 'task_type', 'status', 'user_prompt', 'assistant_message',
    'context', 'snapshot_draft', 'snapshot_meta', 'cost_usd', 'duration_ms', 'error',
])]
class AssistantTurn extends Model
{
    /** @use HasFactory<AssistantTurnFactory> */
    use HasFactory;

    protected static function newFactory(): AssistantTurnFactory
    {
        return AssistantTurnFactory::new();
    }

    /** @return BelongsTo<AssistantSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AssistantSession::class, 'assistant_session_id');
    }

    /** @return HasMany<AssistantChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(AssistantChange::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'task_type' => AssistantTaskType::class,
            'status' => AssistantTurnStatus::class,
            'context' => 'array',
            'snapshot_meta' => 'array',
            'cost_usd' => 'decimal:6',
            'duration_ms' => 'integer',
        ];
    }
}
