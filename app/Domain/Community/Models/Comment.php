<?php

declare(strict_types=1);

namespace App\Domain\Community\Models;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Policies\CommentPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property string|null $body
 * @property string $body_hash
 * @property CommentStatus $status
 * @property CarbonInterface $edit_deadline_at
 * @property int|null $moderated_by_id
 * @property string|null $moderation_reason
 * @property CarbonInterface $submitted_at
 * @property CarbonInterface|null $approved_at
 * @property CarbonInterface|null $rejected_at
 * @property CarbonInterface|null $deleted_at
 * @property CarbonInterface|null $body_erased_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'user_id', 'body', 'body_hash', 'status', 'edit_deadline_at', 'submitted_at',
])]
#[UsePolicy(CommentPolicy::class)]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'status' => CommentStatus::Pending->value,
    ];

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_id');
    }

    public function isEditableBy(User $user): bool
    {
        return $this->user_id === $user->id
            && $this->status !== CommentStatus::Deleted
            && $this->edit_deadline_at->isFuture();
    }

    public function displayBody(): string
    {
        return html_entity_decode($this->body ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'edit_deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'deleted_at' => 'datetime',
            'body_erased_at' => 'datetime',
        ];
    }
}
