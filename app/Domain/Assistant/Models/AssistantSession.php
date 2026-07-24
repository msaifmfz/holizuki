<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AssistantSessionStatus;
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
 * @property string|null $active_run_token
 * @property bool $resume_ready
 * @property string|null $conversation_model
 * @property string|null $outline_markdown
 * @property bool $outline_ready
 * @property int $outline_version
 * @property CarbonInterface|null $turn_started_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'claude_session_id', 'status', 'active_run_token', 'resume_ready',
    'conversation_model', 'outline_markdown', 'outline_ready', 'outline_version',
    'turn_started_at',
])]
class AssistantSession extends Model
{
    /** @use HasFactory<AssistantSessionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => AssistantSessionStatus::Idle->value,
        'resume_ready' => false,
        'outline_ready' => false,
        'outline_version' => 0,
    ];

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

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => AssistantSessionStatus::class,
            'resume_ready' => 'boolean',
            'outline_ready' => 'boolean',
            'outline_version' => 'integer',
            'turn_started_at' => 'datetime',
        ];
    }
}
