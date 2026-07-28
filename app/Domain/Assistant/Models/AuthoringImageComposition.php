<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Models;

use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Carbon\CarbonInterface;
use Database\Factories\AuthoringImageCompositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Override;

/**
 * @property int $id
 * @property string $public_id
 * @property int $post_id
 * @property int $plan_id
 * @property int|null $selected_passage_id
 * @property int|null $selected_by_id
 * @property AuthoringImageCompositionUsage $usage
 * @property string|null $section_heading
 * @property int $position
 * @property string|null $passage_markdown
 * @property string|null $passage_input_hash
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'public_id', 'post_id', 'plan_id', 'selected_passage_id', 'selected_by_id',
    'usage', 'section_heading', 'position', 'passage_markdown',
    'passage_input_hash',
])]
#[Table(name: 'assistant_authoring_image_compositions')]
final class AuthoringImageComposition extends Model
{
    /** @use HasFactory<AuthoringImageCompositionFactory> */
    use HasFactory;

    protected static function newFactory(): AuthoringImageCompositionFactory
    {
        return AuthoringImageCompositionFactory::new();
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

    /** @return BelongsTo<AuthoringArtifact, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AuthoringArtifact::class, 'plan_id');
    }

    /** @return BelongsTo<AuthoringArtifact, $this> */
    public function selectedPassage(): BelongsTo
    {
        return $this->belongsTo(AuthoringArtifact::class, 'selected_passage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_id');
    }

    /** @return BelongsToMany<PostMedia, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(
            PostMedia::class,
            'assistant_authoring_image_media',
            'composition_id',
            'post_media_id',
        )->withPivot(['position'])->orderByPivot('position');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'usage' => AuthoringImageCompositionUsage::class,
            'position' => 'integer',
        ];
    }
}
