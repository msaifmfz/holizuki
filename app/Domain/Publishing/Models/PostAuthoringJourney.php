<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\AuthoringJourneyKind;
use App\Domain\Publishing\Enums\AuthoringJourneyStep;
use App\Domain\Publishing\Enums\AuthoringPersonality;
use App\Domain\Publishing\Enums\AuthoringSurface;
use Carbon\CarbonInterface;
use Database\Factories\PostAuthoringJourneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property int $id
 * @property int $post_id
 * @property AuthoringJourneyKind $kind
 * @property AuthoringJourneyStep $current_step
 * @property list<string> $completed_steps
 * @property array<string, mixed> $brief
 * @property AuthoringPersonality $personality
 * @property string|null $custom_instructions
 * @property bool $web_research_enabled
 * @property AuthoringSurface $last_surface
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'post_id', 'kind', 'current_step', 'completed_steps', 'brief', 'personality',
    'custom_instructions', 'web_research_enabled', 'last_surface',
])]
class PostAuthoringJourney extends Model
{
    /** @use HasFactory<PostAuthoringJourneyFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    #[Override]
    protected $attributes = [
        'kind' => AuthoringJourneyKind::Creation->value,
        'current_step' => AuthoringJourneyStep::Idea->value,
        'completed_steps' => '[]',
        'brief' => '{}',
        'personality' => AuthoringPersonality::Friendly->value,
        'web_research_enabled' => false,
        'last_surface' => AuthoringSurface::Guided->value,
    ];

    protected static function newFactory(): PostAuthoringJourneyFactory
    {
        return PostAuthoringJourneyFactory::new();
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
            'kind' => AuthoringJourneyKind::class,
            'current_step' => AuthoringJourneyStep::class,
            'completed_steps' => 'array',
            'brief' => 'array',
            'personality' => AuthoringPersonality::class,
            'web_research_enabled' => 'boolean',
            'last_surface' => AuthoringSurface::class,
        ];
    }
}
