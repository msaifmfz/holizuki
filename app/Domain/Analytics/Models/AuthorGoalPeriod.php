<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\GoalPeriodStatus;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AuthorGoalPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $goal_id
 * @property int $user_id
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property int $target
 * @property int $published_count
 * @property GoalPeriodStatus $status
 * @property CarbonInterface|null $finalized_at
 */
#[Fillable(['goal_id', 'user_id', 'starts_on', 'ends_on', 'target', 'published_count', 'status', 'finalized_at'])]
class AuthorGoalPeriod extends Model
{
    /** @use HasFactory<AuthorGoalPeriodFactory> */
    use HasFactory;

    protected static function newFactory(): AuthorGoalPeriodFactory
    {
        return AuthorGoalPeriodFactory::new();
    }

    /** @return BelongsTo<AuthorGoal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(AuthorGoal::class, 'goal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasMetTarget(): bool
    {
        return $this->published_count >= $this->target;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'target' => 'integer',
            'published_count' => 'integer',
            'status' => GoalPeriodStatus::class,
            'finalized_at' => 'immutable_datetime',
        ];
    }
}
