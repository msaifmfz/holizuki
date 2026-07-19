<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\SavePost;
use App\Domain\Publishing\Models\Post;
use App\Http\Admin\Concerns\BuildsAutosaveResponse;
use App\Http\Admin\Requests\AutosavePostRequest;
use App\Http\Controller;
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
