<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Analytics\Models\AuthorPublication;
use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/** @extends Factory<AuthorPublication> */
class AuthorPublicationFactory extends Factory
{
    #[Override]
    protected $model = AuthorPublication::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['post_id' => Post::factory()->published(), 'author_id' => User::factory(), 'first_published_at' => now()];
    }
}
