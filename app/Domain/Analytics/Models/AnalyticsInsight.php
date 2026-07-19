<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\InsightConfidence;
use App\Domain\Analytics\Enums\InsightStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Carbon\CarbonInterface;
use Database\Factories\AnalyticsInsightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $post_id
 * @property string $rule_id
 * @property string $scope_key
 * @property InsightConfidence $confidence
 * @property InsightStatus $status
 * @property array<string, bool|float|int|string|null> $evidence
 * @property string $observation
 * @property string $suggested_action
 * @property string|null $dismissal_reason
 * @property CarbonInterface $detected_at
 * @property CarbonInterface $last_seen_at
 * @property CarbonInterface|null $dismissed_until
 * @property CarbonInterface|null $completed_at
 */
#[Fillable([
    'user_id', 'post_id', 'rule_id', 'scope_key', 'confidence', 'status', 'evidence',
    'observation', 'suggested_action', 'dismissal_reason', 'detected_at', 'last_seen_at',
    'dismissed_until', 'completed_at',
])]
class AnalyticsInsight extends Model
{
    /** @use HasFactory<AnalyticsInsightFactory> */
    use HasFactory;

    protected static function newFactory(): AnalyticsInsightFactory
    {
        return AnalyticsInsightFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'confidence' => InsightConfidence::class,
            'status' => InsightStatus::class,
            'evidence' => 'array',
            'detected_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'dismissed_until' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
