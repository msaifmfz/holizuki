<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Taxonomy\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Override;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    #[Override]
    protected $model = Category::class;

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
            'description' => fake()->sentence(),
        ];
    }
}
