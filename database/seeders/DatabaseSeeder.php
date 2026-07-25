<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Idempotent so the curated PostSeeder content can be re-seeded over an
        // existing local database. The profile fields are refreshed on every
        // run — the password and other generated attributes are not, so an
        // existing local login keeps working.
        $user = User::query()->firstOrNew(['email' => 'test@example.com']);

        if (! $user->exists) {
            $user->forceFill(User::factory()->raw(['email' => 'test@example.com']));
        }

        $user->forceFill([
            'name' => 'Test User',
            'author_slug' => 'test-user',
            'bio' => 'Travels slowly, books the shoulder season, and pays for every room reviewed here.',
            'social_links' => ['website' => 'https://example.com'],
        ])->save();

        $this->call(PostSeeder::class);
    }
}
