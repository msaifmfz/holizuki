<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
use App\Domain\Assistant\Enums\AssistantTaskType;
use App\Domain\Assistant\Enums\AssistantTurnStatus;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AssistantSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * The per-post AI co-writer session. Holds the Claude Code session id that
 * `--resume` targets and the busy flag guarding one active turn per post.
 *
 * @property int $id
 * @property int $post_id
 * @property string $claude_session_id
 * @property AssistantSessionStatus $status
 * @property CarbonInterface|null $turn_started_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['post_id', 'claude_session_id', 'status', 'turn_started_at'])]
class AssistantSession extends Model
{
    /** @use HasFactory<AssistantSessionFactory> */
    use HasFactory;

    protected static function newFactory(): AssistantSessionFactory
    {
        return AssistantSessionFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return HasMany<AssistantTurn, $this> */
    public function turns(): HasMany
    {
        return $this->hasMany(AssistantTurn::class);
    }

    /**
     * Whether a conversational turn has completed before the given one —
     * i.e. whether the Claude Code session file exists to `--resume`.
     */
    public function hasConversationBefore(int $excludedTurnId): bool
    {
        return $this->turns()
            ->whereKeyNot($excludedTurnId)
            ->where('status', AssistantTurnStatus::Completed)
            ->whereIn('task_type', [AssistantTaskType::Chat, AssistantTaskType::Outline])
            ->exists();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => AssistantSessionStatus::class,
            'turn_started_at' => 'datetime',
        ];
    }
}
