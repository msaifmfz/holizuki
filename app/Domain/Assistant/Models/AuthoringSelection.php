<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AuthoringSelectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $selected_plan_id
 * @property int|null $selected_draft_id
 * @property int|null $selected_by_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['post_id', 'selected_plan_id', 'selected_draft_id', 'selected_by_id'])]
#[Table(name: 'assistant_authoring_selections')]
final class AuthoringSelection extends Model
{
    /** @use HasFactory<AuthoringSelectionFactory> */
    use HasFactory;

    protected static function newFactory(): AuthoringSelectionFactory
    {
        return AuthoringSelectionFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return BelongsTo<AuthoringArtifact, $this> */
    public function selectedPlan(): BelongsTo
    {
        return $this->belongsTo(AuthoringArtifact::class, 'selected_plan_id');
    }

    /** @return BelongsTo<AuthoringArtifact, $this> */
    public function selectedDraft(): BelongsTo
    {
        return $this->belongsTo(AuthoringArtifact::class, 'selected_draft_id');
    }

    /** @return BelongsTo<User, $this> */
    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_id');
    }
}
