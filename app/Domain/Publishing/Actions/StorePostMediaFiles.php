<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Enums\PostMediaOrigin;
use App\Domain\Publishing\Enums\PostMediaStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StorePostMediaFiles
{
    /**
     * @param  list<UploadedFile>  $images
     * @return Collection<int, PostMedia>
     */
    public function handle(Post $post, array $images): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($post, $images, &$storedPaths): Collection {
                Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

                if (
                    PostMedia::query()->whereBelongsTo($post)->count() + count($images)
                    > PostMedia::MAX_PER_POST
                ) {
                    throw ValidationException::withMessages([
                        'images' => PostMedia::MAX_PER_POST_MESSAGE,
                    ]);
                }

                $mediaItems = new Collection;

                foreach ($images as $image) {
                    $dimensions = getimagesize($image->getRealPath());

                    if ($dimensions === false) {
                        throw new RuntimeException('The image dimensions could not be read.');
                    }

                    $path = $image->store('posts/'.$post->id.'/media', 'public');

                    if ($path === false) {
                        throw new RuntimeException('The image could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $mediaItems->push(PostMedia::create([
                        'post_id' => $post->id,
                        'path' => $path,
                        'width' => $dimensions[0],
                        'height' => $dimensions[1],
                        'display_name' => $this->displayName($image),
                        'origin' => PostMediaOrigin::LibraryUpload,
                        'status' => PostMediaStatus::Ready,
                    ]));
                }

                return $mediaItems;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

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
