<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\SyncStatus;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $run_id
 * @property string $command
 * @property SyncStatus $status
 * @property CarbonInterface|null $starts_on
 * @property CarbonInterface|null $ends_on
 * @property int $attempt
 * @property int $request_count
 * @property int $page_count
 * @property int $row_count
 * @property array<string, mixed>|null $quota
 * @property string|null $sanitized_error
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $completed_at
 */
#[Fillable([
    'run_id', 'command', 'status', 'starts_on', 'ends_on', 'attempt', 'request_count',
    'page_count', 'row_count', 'quota', 'sanitized_error', 'started_at', 'completed_at',
])]
class AnalyticsSyncRun extends Model
{
    /** @use HasFactory<AnalyticsSyncRunFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsSyncRunFactory
    {
        return AnalyticsSyncRunFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'quota' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
