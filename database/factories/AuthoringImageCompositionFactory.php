<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Enums\AuthoringImageCompositionUsage;
use App\Domain\Assistant\Models\AuthoringArtifact;
use App\Domain\Assistant\Models\AuthoringImageComposition;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/** @extends Factory<AuthoringImageComposition> */
final class AuthoringImageCompositionFactory extends Factory
{
    #[Override]
    protected $model = AuthoringImageComposition::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'post_id' => Post::factory(),
            'plan_id' => AuthoringArtifact::factory(),
            'selected_passage_id' => null,
            'selected_by_id' => User::factory(),
            'usage' => AuthoringImageCompositionUsage::Include,
            'section_heading' => fake()->sentence(3),
            'position' => 1,
            'passage_markdown' => null,
            'passage_input_hash' => null,
        ];
    }
}
