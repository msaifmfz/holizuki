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
 * @property string $run_token
 * @property string $request_id
 * @property AssistantTaskType $task_type
 * @property AssistantTurnStatus $status
 * @property string $user_prompt
 * @property string|null $assistant_message
 * @property array<string, mixed>|null $context
 * @property string|null $snapshot_draft
 * @property array<string, mixed>|null $snapshot_meta
 * @property numeric-string|null $cost_usd
 * @property int|null $duration_ms
 * @property string|null $result_subtype
 * @property string|null $stop_reason
 * @property array<string, mixed>|null $usage
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonInterface|null $retry_at
 * @property CarbonInterface|null $cancel_requested_at
 * @property CarbonInterface|null $finished_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'assistant_session_id', 'run_token', 'request_id', 'task_type', 'status',
    'user_prompt', 'assistant_message', 'context', 'snapshot_draft', 'snapshot_meta',
    'result_subtype', 'stop_reason', 'usage', 'cost_usd', 'duration_ms',
    'error_code', 'error_message', 'retry_at', 'cancel_requested_at', 'finished_at',
])]
class AssistantTurn extends Model
{
    /** @use HasFactory<AssistantTurnFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => AssistantTurnStatus::Running->value,
    ];

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
            'usage' => 'array',
            'cost_usd' => 'decimal:6',
            'duration_ms' => 'integer',
            'retry_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
