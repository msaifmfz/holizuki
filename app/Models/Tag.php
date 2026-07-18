<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}
