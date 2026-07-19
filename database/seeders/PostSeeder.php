<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Models\Post;
use App\Domain\Taxonomy\Models\Category;
use App\Domain\Taxonomy\Models\Tag;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(RebuildPostMetadata $rebuildPostMetadata): void
    {
        $categories = Category::factory()->count(4)->create();
        $tags = Tag::factory()->count(10)->create();

        $published = Post::factory()->count(6)->published()->create(['category_id' => fn (): int => $categories->random()->id]);
        $scheduled = Post::factory()->count(3)->scheduled()->create(['category_id' => fn (): int => $categories->random()->id]);
        $drafts = Post::factory()->count(3)->create(['category_id' => fn (): int => $categories->random()->id]);

        foreach ($published->concat($scheduled)->concat($drafts) as $post) {
            $post->tags()->attach($tags->random(random_int(2, 4))->pluck('id'));
            $rebuildPostMetadata->handle($post->unsetRelations());
        }
    }
}
