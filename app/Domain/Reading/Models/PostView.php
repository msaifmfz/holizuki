<?php

declare(strict_types=1);

namespace App\Domain\Reading\Models;

use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\PostViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property CarbonInterface $viewed_on
 * @property string $visitor_hash
 * @property CarbonInterface $created_at
 */
#[Fillable(['post_id', 'viewed_on', 'visitor_hash'])]
class PostView extends Model
{
    /** @use HasFactory<PostViewFactory> */
    use HasFactory, MassPrunable;

    protected static function newFactory(): PostViewFactory
    {
        return PostViewFactory::new();
    }

    public const UPDATED_AT = null;

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return Builder<PostView> */
    public function prunable(): Builder
    {
        $retentionDays = max(1, config()->integer('blog.view_retention_days', 90));

        return self::query()->where('viewed_on', '<', today()->subDays($retentionDays));
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
            'created_at' => 'datetime',
        ];
    }
}
