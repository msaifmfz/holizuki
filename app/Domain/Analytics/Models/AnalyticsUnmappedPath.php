<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use Carbon\CarbonInterface;
use Database\Factories\AnalyticsUnmappedPathFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property string $path
 * @property int $readers
 * @property int $page_views
 * @property CarbonInterface $first_seen_at
 * @property CarbonInterface $last_seen_at
 */
#[Fillable(['path', 'readers', 'page_views', 'first_seen_at', 'last_seen_at'])]
class AnalyticsUnmappedPath extends Model
{
    /** @use HasFactory<AnalyticsUnmappedPathFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsUnmappedPathFactory
    {
        return AnalyticsUnmappedPathFactory::new();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['first_seen_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
