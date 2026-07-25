<?php

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Models\Post;

/**
 * The two reader-facing surfaces the public smoke test cannot reach, because
 * both need an authenticated session.
 *
 * The preview case exists because of a bug it would have caught. `.public-tokens`
 * is meant to give the admin's post preview the reader palette while leaving the
 * console chrome around it alone, and for a long time it silently did not:
 * Tailwind resolved the `--color-*` indirection once at `:root`, so the subtree
 * inherited the console's white and its system serif no matter what the scoped
 * block declared. Nothing failed, because nothing looked.
 */
it('renders the reader account page on the reader surface', function (): void {
    $reader = User::factory()->reader()->create();

    $this->actingAs($reader);

    visit('/account')
        ->wait(1)
        ->assertSee('Your account')
        ->assertSee('Change password')
        ->assertSee('Delete account')
        // The reader surface must be active here, not the console's.
        ->assertScript(
            "document.documentElement.classList.contains('public-surface')",
            true,
        )
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});

it('gives the admin post preview the reader palette without restyling the console', function (): void {
    $admin = User::factory()->create();
    $post = Post::factory()->for($admin, 'author')->create([
        'title' => 'A previewed article',
    ]);

    $this->actingAs($admin);

    $page = visit("/posts/{$post->id}/preview")->wait(1);

    $page->assertSee('A previewed article')
        // The scoped token subtree resolves the reader display and body faces.
        ->assertScript(
            "getComputedStyle(document.querySelector('.public-tokens')).getPropertyValue('--display-family').includes('Archivo')",
            true,
        )
        ->assertScript(
            "getComputedStyle(document.querySelector('.public-tokens')).getPropertyValue('--body-family').includes('Newsreader')",
            true,
        )
        // And those variables actually reach the rendered text, which is the
        // part that was broken. Assert on the prose and a heading inside it
        // rather than on the `.public-tokens` wrapper: the wrapper itself sets
        // no font utility, so it legitimately inherits the console's sans and
        // comparing against that proves nothing.
        ->assertScript(
            "getComputedStyle(document.querySelector('.public-rich-text')).fontFamily.includes('Newsreader')",
            true,
        )
        // The preview paints the reader background, not the console's white.
        ->assertScript(
            "getComputedStyle(document.querySelector('.public-tokens')).backgroundColor !== getComputedStyle(document.body).backgroundColor",
            true,
        )
        ->assertNoSmoke();
});
