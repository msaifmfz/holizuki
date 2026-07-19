<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $code
 * @property string $scope_key
 * @property array<string, bool|float|int|string|null>|null $evidence
 * @property CarbonInterface $achieved_at
 */
#[Fillable(['user_id', 'code', 'scope_key', 'evidence', 'achieved_at'])]
class AnalyticsMilestone extends Model
{
    /** @use HasFactory<AnalyticsMilestoneFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsMilestoneFactory
    {
        return AnalyticsMilestoneFactory::new();
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
        return ['evidence' => 'array', 'achieved_at' => 'immutable_datetime'];
    }
}
