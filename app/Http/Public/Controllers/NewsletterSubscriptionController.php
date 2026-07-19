<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Community\Actions\StartSubscription;
use App\Domain\Publishing\Models\Post;
use App\Http\Controller;
use App\Http\Public\Requests\SubscribeRequest;
use Illuminate\Http\RedirectResponse;

class NewsletterSubscriptionController extends Controller
{
    public function store(SubscribeRequest $request, StartSubscription $startSubscription): RedirectResponse
    {
        $sourcePostId = $request->integer('source_post_id');
        $sourcePost = $sourcePostId > 0
            ? Post::query()->published()->find($sourcePostId)
            : null;

        $startSubscription->handle(
            email: $request->string('email')->toString(),
            sourcePost: $sourcePost,
            sourceLocation: $request->string('source_location')->toString(),
            consentVersion: $request->string('consent_version')->toString(),
        );

        return back()->with('success', 'Check your email to confirm your subscription.');
    }
}
