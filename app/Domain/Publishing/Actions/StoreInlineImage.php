<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $path = $image->store('posts/'.$post->id.'/inline', 'public');

        if ($path === false) {
            throw new RuntimeException('The inline image could not be stored.');
        }

        try {
            return PostMedia::create([
                'post_id' => $post->id,
                'path' => $path,
                'width' => $dimensions[0],
                'height' => $dimensions[1],
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }
    }
}
