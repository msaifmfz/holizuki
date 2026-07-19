<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsSnapshotPreparationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $preparation_key
 * @property int|null $requested_by_id
 * @property string $scope_key
 * @property CarbonInterface $starts_on
 * @property CarbonInterface $ends_on
 * @property 'queued'|'preparing'|'ready'|'failed' $status
 * @property string|null $sanitized_error
 * @property CarbonInterface|null $completed_at
 */
#[Fillable([
    'preparation_key', 'requested_by_id', 'scope_key', 'starts_on', 'ends_on', 'status',
    'sanitized_error', 'completed_at',
])]
class AnalyticsSnapshotPreparation extends Model
{
    /** @use HasFactory<AnalyticsSnapshotPreparationFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsSnapshotPreparationFactory
    {
        return AnalyticsSnapshotPreparationFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'completed_at' => 'datetime'];
    }
}
