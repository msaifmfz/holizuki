<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AuthoringTaskKind;
use App\Domain\Assistant\Enums\AuthoringTaskStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AuthoringTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property string $public_id
 * @property string $request_id
 * @property int $post_id
 * @property int|null $requested_by_id
 * @property int|null $assistant_turn_id
 * @property AuthoringTaskKind $kind
 * @property AuthoringTaskStatus $status
 * @property int $base_lock_version
 * @property string $input_hash
 * @property array<string, mixed> $context
 * @property int $attempts
 * @property string|null $error_code
 * @property string|null $error_message
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'public_id', 'request_id', 'post_id', 'requested_by_id', 'assistant_turn_id',
    'kind', 'status', 'base_lock_version', 'input_hash', 'context', 'attempts',
    'error_code', 'error_message', 'started_at', 'finished_at',
])]
#[Table(name: 'assistant_authoring_tasks')]
final class AuthoringTask extends Model
{
    /** @use HasFactory<AuthoringTaskFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => AuthoringTaskStatus::Queued->value,
        'attempts' => 0,
    ];

    protected static function newFactory(): AuthoringTaskFactory
    {
        return AuthoringTaskFactory::new();
    }

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<AssistantTurn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(AssistantTurn::class, 'assistant_turn_id');
    }

    /** @return HasMany<AuthoringArtifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(AuthoringArtifact::class, 'assistant_authoring_task_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AuthoringTaskStatus::Queued,
            AuthoringTaskStatus::Running,
        ]);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => AuthoringTaskKind::class,
            'status' => AuthoringTaskStatus::class,
            'base_lock_version' => 'integer',
            'context' => 'array',
            'attempts' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
