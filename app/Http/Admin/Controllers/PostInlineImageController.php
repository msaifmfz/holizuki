<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\StoreInlineImage;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Requests\StoreInlineImageRequest;
use App\Http\Controller;
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
