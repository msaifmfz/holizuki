<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AuthorPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $author_id
 * @property CarbonInterface $first_published_at
 */
#[Fillable(['post_id', 'author_id', 'first_published_at'])]
class AuthorPublication extends Model
{
    /** @use HasFactory<AuthorPublicationFactory> */
    use HasFactory;

    protected static function newFactory(): AuthorPublicationFactory
    {
        return AuthorPublicationFactory::new();
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['first_published_at' => 'immutable_datetime'];
    }
}
