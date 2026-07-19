<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use Carbon\CarbonInterface;
use Database\Factories\PostMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property string $path
 * @property int $width
 * @property int $height
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['post_id', 'path', 'width', 'height'])]
class PostMedia extends Model
{
    /** @use HasFactory<PostMediaFactory> */
    use HasFactory;

    protected static function newFactory(): PostMediaFactory
    {
        return PostMediaFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }
}
