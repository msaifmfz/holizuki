<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Enums\AuthoringJourneyKind;
use App\Domain\Publishing\Enums\AuthoringJourneyStep;
use App\Domain\Publishing\Enums\AuthoringPersonality;
use App\Domain\Publishing\Enums\AuthoringSurface;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostAuthoringJourney;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PostAuthoringJourney>
 */
class PostAuthoringJourneyFactory extends Factory
{
    #[Override]
    protected $model = PostAuthoringJourney::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'kind' => AuthoringJourneyKind::Creation,
            'current_step' => AuthoringJourneyStep::Idea,
            'completed_steps' => [],
            'brief' => [],
            'personality' => AuthoringPersonality::Friendly,
            'custom_instructions' => null,
            'web_research_enabled' => false,
            'last_surface' => AuthoringSurface::Guided,
        ];
    }

    public function improvement(): static
    {
        return $this->state(fn (): array => [
            'kind' => AuthoringJourneyKind::Improvement,
        ]);
    }
}
