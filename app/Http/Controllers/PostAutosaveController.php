<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\SavePost;
use App\Concerns\BuildsAutosaveResponse;
use App\Http\Requests\AutosavePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostAutosaveController extends Controller
{
    use BuildsAutosaveResponse;

    public function __invoke(AutosavePostRequest $request, Post $post, SavePost $savePost): JsonResponse
    {
        $updatedPost = $savePost->handle(
            $post,
            $request->validated(),
            $request->authenticatedUser(),
            force: $request->boolean('force'),
        );

        return response()->json($this->autosavePayload($updatedPost));
    }
}
