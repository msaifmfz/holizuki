<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\GoalCadence;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AuthorGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property GoalCadence $cadence
 * @property int $target
 * @property CarbonInterface $effective_from
 * @property CarbonInterface|null $effective_until
 * @property CarbonInterface|null $disabled_at
 * @property Collection<int, AuthorGoalPeriod> $periods
 */
#[Fillable(['user_id', 'cadence', 'target', 'effective_from', 'effective_until', 'disabled_at'])]
class AuthorGoal extends Model
{
    /** @use HasFactory<AuthorGoalFactory> */
    use HasFactory;

    protected static function newFactory(): AuthorGoalFactory
    {
        return AuthorGoalFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AuthorGoalPeriod, $this> */
    public function periods(): HasMany
    {
        return $this->hasMany(AuthorGoalPeriod::class, 'goal_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'cadence' => GoalCadence::class,
            'target' => 'integer',
            'effective_from' => 'immutable_date',
            'effective_until' => 'immutable_date',
            'disabled_at' => 'immutable_datetime',
        ];
    }
}
