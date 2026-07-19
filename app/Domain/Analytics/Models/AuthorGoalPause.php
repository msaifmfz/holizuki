<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AuthorGoalPauseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property string|null $reason
 */
#[Fillable(['user_id', 'starts_on', 'ends_on', 'reason'])]
class AuthorGoalPause extends Model
{
    /** @use HasFactory<AuthorGoalPauseFactory> */
    use HasFactory;

    protected static function newFactory(): AuthorGoalPauseFactory
    {
        return AuthorGoalPauseFactory::new();
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
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }
}
