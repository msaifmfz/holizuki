<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Posts\FeaturePost;
use App\Actions\Posts\UnfeaturePost;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PostFeatureController extends Controller
{
    public function store(Post $post, FeaturePost $featurePost): Response
    {
        Gate::authorize('update', $post);
        $featurePost->handle($post);

        return response()->noContent();
    }

    public function destroy(Post $post, UnfeaturePost $unfeaturePost): Response
    {
        Gate::authorize('update', $post);
        $unfeaturePost->handle($post);

        return response()->noContent();
    }
}
