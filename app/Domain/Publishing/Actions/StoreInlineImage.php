<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Enums\PostMediaStoryPriority;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreInlineImage
{
    public function handle(Post $post, UploadedFile $image): PostMedia
    {
        $dimensions = getimagesize($image->getRealPath());

        if ($dimensions === false
            || $dimensions[0] < 1
            || $dimensions[1] < 1
            || $dimensions[0] > 10_000
            || $dimensions[1] > 10_000) {
            throw new RuntimeException('The inline image dimensions could not be read.');
        }

        $path = null;

        try {
            return DB::transaction(function () use ($post, $image, $dimensions, &$path): PostMedia {
                Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

                if (PostMedia::query()->whereBelongsTo($post)->count() >= PostMedia::MAX_PER_POST) {
                    throw ValidationException::withMessages([
                        'image' => PostMedia::MAX_PER_POST_MESSAGE,
                    ]);
                }

                $path = $image->store('posts/'.$post->id.'/inline', 'public');

                if ($path === false) {
                    throw new RuntimeException('The inline image could not be stored.');
                }

                return PostMedia::create([
                    'post_id' => $post->id,
                    'path' => $path,
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                    'display_name' => $this->displayName($image),
                    'origin' => PostMediaOrigin::InlineEditor,
                    'status' => PostMediaStatus::Ready,
                    'story_priority' => PostMediaStoryPriority::Required,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    private function displayName(UploadedFile $image): string
    {
        $name = Str::of($image->getClientOriginalName())
            ->replace('\\', '/')
            ->afterLast('/')
            ->beforeLast('.')
            ->trim()
            ->limit(255, '')
            ->toString();

        return $name === '' ? __('Untitled image') : $name;
    }
}
