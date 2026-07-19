<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AuthorActivityEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $post_id
 * @property string $event_id
 * @property string $event_key
 * @property array<string, bool|float|int|string|null>|null $metadata
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface $expires_at
 */
#[Fillable(['user_id', 'post_id', 'event_id', 'event_key', 'metadata', 'occurred_at', 'expires_at'])]
class AuthorActivityEvent extends Model
{
    /** @use HasFactory<AuthorActivityEventFactory> */
    use HasFactory;

    protected static function newFactory(): AuthorActivityEventFactory
    {
        return AuthorActivityEventFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
