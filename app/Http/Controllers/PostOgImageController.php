<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\StoreOgImage;
use App\Http\Requests\PostLockVersionRequest;
use App\Http\Requests\StoreOgImageRequest;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;

class PostOgImageController extends Controller
{
    public function store(StoreOgImageRequest $request, Post $post, StoreOgImage $storeImage): JsonResponse
    {
        $image = $request->file('image');
        abort_if($image === null, 422);
        $updatedPost = $storeImage->handle(
            $post,
            $request->authenticatedUser(),
            $image,
            $request->integer('lock_version'),
        );

        return response()->json([
            'og_image_url' => Seo::postOgImageUrl($updatedPost),
            'lock_version' => $updatedPost->lock_version,
            'updated_at' => $updatedPost->updated_at?->toISOString(),
        ]);
    }

    public function destroy(PostLockVersionRequest $request, Post $post, StoreOgImage $storeImage): JsonResponse
    {
        $updatedPost = $storeImage->remove($post, $request->authenticatedUser(), $request->integer('lock_version'));

        return response()->json([
            'og_image_url' => null,
            'lock_version' => $updatedPost->lock_version,
            'updated_at' => $updatedPost->updated_at?->toISOString(),
        ]);
    }
}
