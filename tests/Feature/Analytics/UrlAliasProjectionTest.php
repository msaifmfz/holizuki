<?php

declare(strict_types=1);

use App\Domain\Analytics\Actions\ProjectAnalyticsUrlAliases;
use App\Domain\Analytics\Models\AnalyticsUrlAlias;
use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostRedirect;

it('reconciles canonical and redirected post paths idempotently', function (): void {
    $post = Post::factory()->published()->create(['slug' => 'current-slug']);
    PostRedirect::query()->create(['old_slug' => 'old-slug', 'post_id' => $post->id]);
    $projection = resolve(ProjectAnalyticsUrlAliases::class);

    $projection->reconcile();
    $projection->reconcile();

    expect(AnalyticsUrlAlias::query()->count())->toBe(2)
        ->and(AnalyticsUrlAlias::query()->where('path', '/posts/current-slug')->firstOrFail()->is_canonical)->toBeTrue()
        ->and(AnalyticsUrlAlias::query()->where('path', '/posts/current-slug')->firstOrFail()->retired_at)->toBeNull()
        ->and(AnalyticsUrlAlias::query()->where('path', '/posts/old-slug')->firstOrFail()->is_canonical)->toBeFalse()
        ->and(AnalyticsUrlAlias::query()->where('path', '/posts/old-slug')->firstOrFail()->content_key)->toBe('post:'.$post->id);
});

it('retires a canonical path when a post leaves the public site', function (): void {
    $post = Post::factory()->published()->create(['slug' => 'retired-post']);
    $projection = resolve(ProjectAnalyticsUrlAliases::class);
    $projection->handle($post);

    $post->forceFill(['status' => PostStatus::Draft])->save();
    $projection->handle($post->refresh());

    expect(AnalyticsUrlAlias::query()->where('path', '/posts/retired-post')->firstOrFail()->retired_at)->not->toBeNull();
});
