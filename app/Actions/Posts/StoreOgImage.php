<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Concerns\ResolvesLockedPost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreOgImage
{
    use ResolvesLockedPost;

    public function handle(Post $post, User $editor, UploadedFile $image, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $image, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $path = $image->store('posts/'.$current->id, 'public');

            if ($path === false) {
                throw new RuntimeException('The social sharing image could not be stored.');
            }

            $previousPath = $current->og_image_path;

            try {
                $current->og_image_path = $path;
                $current->updated_by_id = $editor->id;
                $current->lock_version++;
                $current->save();
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($path);

                throw $exception;
            }

            $this->deleteUnreferencedFile($current, $previousPath);

            return $current->refresh();
        });
    }

    public function remove(Post $post, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);

            $previousPath = $current->og_image_path;
            $current->og_image_path = null;
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->deleteUnreferencedFile($current, $previousPath);

            return $current->refresh();
        });
    }

    private function deleteUnreferencedFile(Post $post, ?string $path): void
    {
        if ($path === null || $post->revisions()->where('og_image_path', $path)->exists()) {
            return;
        }

        // Deleting files is not transactional; defer until the surrounding
        // transaction commits so a rollback cannot orphan the referenced image.
        DB::afterCommit(fn () => Storage::disk('public')->delete($path));
    }
}
