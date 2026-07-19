<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    #[Override]
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
