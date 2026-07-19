<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Publishing\Enums\PostStatus;
use App\Domain\Publishing\Models\Post;
use App\Domain\Reading\Models\PostView;
use App\Domain\Reading\Support\ReaderIdentity;
use App\Http\Controller;
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
