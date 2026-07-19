<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\FreshnessState;
use App\Domain\Analytics\Enums\MomentumLevel;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsMomentumSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property CarbonInterface $scored_on
 * @property int|null $score
 * @property MomentumLevel|null $level
 * @property array<string, mixed> $components
 * @property FreshnessState $freshness
 * @property CarbonInterface|null $data_freshness_at
 * @property CarbonInterface $calculated_at
 */
#[Fillable(['user_id', 'scored_on', 'score', 'level', 'components', 'freshness', 'data_freshness_at', 'calculated_at'])]
class AnalyticsMomentumSnapshot extends Model
{
    /** @use HasFactory<AnalyticsMomentumSnapshotFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsMomentumSnapshotFactory
    {
        return AnalyticsMomentumSnapshotFactory::new();
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
        return [
            'scored_on' => 'immutable_date',
            'score' => 'integer',
            'level' => MomentumLevel::class,
            'components' => 'array',
            'freshness' => FreshnessState::class,
            'data_freshness_at' => 'immutable_datetime',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
