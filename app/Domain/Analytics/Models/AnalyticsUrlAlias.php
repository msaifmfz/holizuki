<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsUrlAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $path
 * @property int|null $post_id
 * @property string $content_key
 * @property bool $is_canonical
 * @property CarbonInterface|null $retired_at
 */
#[Fillable(['path', 'post_id', 'content_key', 'is_canonical', 'retired_at'])]
class AnalyticsUrlAlias extends Model
{
    /** @use HasFactory<AnalyticsUrlAliasFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsUrlAliasFactory
    {
        return AnalyticsUrlAliasFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['is_canonical' => 'boolean', 'retired_at' => 'datetime'];
    }
}
