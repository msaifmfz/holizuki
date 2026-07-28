<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Assistant\Models\AuthoringSelection;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AuthoringSelection> */
final class AuthoringSelectionFactory extends Factory
{
    #[Override]
    protected $model = AuthoringSelection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'selected_plan_id' => null,
            'selected_draft_id' => null,
            'selected_by_id' => User::factory(),
        ];
    }
}
