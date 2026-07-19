<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\StoreInlineImage;
use App\Http\Requests\StoreInlineImageRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PostInlineImageController extends Controller
{
    public function store(StoreInlineImageRequest $request, Post $post, StoreInlineImage $storeImage): JsonResponse
    {
        $image = $request->file('image');
        abort_if($image === null, 422);
        $media = $storeImage->handle($post, $image);

        return response()->json([
            'id' => $media->id,
            'url' => Storage::disk('public')->url($media->path),
            'width' => $media->width,
            'height' => $media->height,
        ], 201);
    }
}
