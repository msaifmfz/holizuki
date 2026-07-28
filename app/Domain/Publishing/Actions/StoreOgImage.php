<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Concerns\ResolvesLockedPost;
use App\Domain\Publishing\Enums\PostRevisionEvent;
use App\Domain\Publishing\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreOgImage
{
    use ResolvesLockedPost;

    public function __construct(private readonly CreatePostRevision $createRevision) {}

    public function handle(Post $post, User $editor, UploadedFile $image, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $image, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);
            $draft = $current->draft()->firstOrFail();

            $path = $image->store('posts/'.$current->id, 'public');

            if ($path === false) {
                throw new RuntimeException('The social sharing image could not be stored.');
            }

            $previousPath = $draft->og_image_path;

            if ($previousPath !== null) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ImageChanged,
                    $draft->snapshot(),
                );
            }

            try {
                $draft->og_image_path = $path;
                $draft->save();
                $current->updated_by_id = $editor->id;
                $current->lock_version++;
                $current->save();
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($path);

                throw $exception;
            }

            $this->deleteUnreferencedFile($current, $previousPath);

            return $current->refresh()->load('draft');
        });
    }

    public function remove(Post $post, User $editor, int $expectedVersion): Post
    {
        return DB::transaction(function () use ($post, $editor, $expectedVersion): Post {
            $current = $this->lockedPost($post, $expectedVersion);
            $draft = $current->draft()->firstOrFail();

            $previousPath = $draft->og_image_path;

            if ($previousPath !== null) {
                $this->createRevision->handle(
                    $current,
                    $editor,
                    PostRevisionEvent::ImageChanged,
                    $draft->snapshot(),
                );
            }

            $draft->og_image_path = null;
            $draft->save();
            $current->updated_by_id = $editor->id;
            $current->lock_version++;
            $current->save();

            $this->deleteUnreferencedFile($current, $previousPath);

            return $current->refresh()->load('draft');
        });
    }

    private function deleteUnreferencedFile(Post $post, ?string $path): void
    {
        if (
            in_array($path, [null, $post->og_image_path, $post->draft()->value('og_image_path')], true)
            || $post->revisions()->referencingPath($path)->exists()
        ) {
            return;
        }

        // Deleting files is not transactional; defer until the surrounding
        // transaction commits so a rollback cannot orphan the referenced image.
        DB::afterCommit(fn () => Storage::disk('public')->delete($path));
    }
}
