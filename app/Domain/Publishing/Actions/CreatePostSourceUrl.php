<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;

final class CreatePostSourceUrl
{
    public function handle(Post $post, User $creator, ?string $title, string $url): PostSource
    {
        $normalizedUrl = trim($url);
        $normalizedTitle = trim((string) $title);

        if ($normalizedTitle === '') {
            $normalizedTitle = (string) parse_url($normalizedUrl, PHP_URL_HOST);
        }

        return PostSource::create([
            'post_id' => $post->id,
            'created_by_id' => $creator->id,
            'kind' => PostSourceKind::Url,
            'title' => $normalizedTitle,
            'url' => $normalizedUrl,
        ]);
    }
}
