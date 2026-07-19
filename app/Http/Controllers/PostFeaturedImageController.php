<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\StoreFeaturedImage;
use App\Http\Requests\PostLockVersionRequest;
use App\Http\Requests\StoreFeaturedImageRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PostFeaturedImageController extends Controller
{
    public function store(StoreFeaturedImageRequest $request, Post $post, StoreFeaturedImage $storeImage): JsonResponse
    {
        $image = $request->file('image');
        abort_if($image === null, 422);
        $updatedPost = $storeImage->handle(
            $post,
            $request->authenticatedUser(),
            $image,
            $request->string('alt_text')->toString(),
            $request->integer('lock_version'),
        );

        return response()->json([
            'featured_image_url' => Storage::disk('public')->url((string) $updatedPost->featured_image_path),
            'featured_image_alt' => $updatedPost->featured_image_alt,
            'featured_image_caption' => $updatedPost->featured_image_caption,
            'lock_version' => $updatedPost->lock_version,
            'updated_at' => $updatedPost->updated_at?->toISOString(),
        ]);
    }

    public function destroy(PostLockVersionRequest $request, Post $post, StoreFeaturedImage $storeImage): JsonResponse
    {
        $updatedPost = $storeImage->remove($post, $request->authenticatedUser(), $request->integer('lock_version'));

        return response()->json([
            'featured_image_url' => null,
            'featured_image_alt' => null,
            'featured_image_caption' => null,
            'lock_version' => $updatedPost->lock_version,
            'updated_at' => $updatedPost->updated_at?->toISOString(),
        ]);
    }
}
