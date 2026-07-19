<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostView;
use App\Support\ReaderIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PostViewEventController extends Controller
{
    public function __invoke(Request $request, Post $post): Response
    {
        abort_unless($post->status === PostStatus::Published, 404);

        $viewedOn = today()->toDateString();
        $visitorHash = ReaderIdentity::dailyHash($request, $viewedOn);

        PostView::query()->insertOrIgnore([
            'post_id' => $post->id,
            'viewed_on' => $viewedOn,
            'visitor_hash' => $visitorHash,
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
