<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Identity\Models\User;
use Database\Factories\AnalyticsSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 * @property int|null $updated_by_id
 */
#[Fillable(['key', 'value', 'updated_by_id'])]
class AnalyticsSetting extends Model
{
    /** @use HasFactory<AnalyticsSettingFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsSettingFactory
    {
        return AnalyticsSettingFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
