<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Support\Str;

final class CreatePostSourceNote
{
    public function handle(Post $post, User $creator, ?string $title, string $content): PostSource
    {
        $normalizedContent = trim($content);
        $normalizedTitle = trim((string) $title);

        if ($normalizedTitle === '') {
            $normalizedTitle = Str::limit(Str::squish($normalizedContent), 80, '');
        }

        return PostSource::create([
            'post_id' => $post->id,
            'created_by_id' => $creator->id,
            'kind' => PostSourceKind::Note,
            'title' => $normalizedTitle === '' ? __('Untitled note') : $normalizedTitle,
            'content' => $normalizedContent,
        ]);
    }
}
