<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Publishing\Actions\FeaturePost;
use App\Domain\Publishing\Actions\UnfeaturePost;
use App\Domain\Publishing\Exceptions\CannotFeatureUnpublishedPost;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PostFeatureController extends Controller
{
    public function store(Post $post, FeaturePost $featurePost): Response
    {
        Gate::authorize('update', $post);

        try {
            $featurePost->handle($post);
        } catch (CannotFeatureUnpublishedPost) {
            throw ValidationException::withMessages([
                'post' => __('Only published posts can be featured.'),
            ]);
        }

        return response()->noContent();
    }

    public function destroy(Post $post, UnfeaturePost $unfeaturePost): Response
    {
        Gate::authorize('update', $post);
        $unfeaturePost->handle($post);

        return response()->noContent();
    }
}
