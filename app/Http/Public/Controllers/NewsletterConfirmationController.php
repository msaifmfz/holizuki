<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Community\Actions\ConfirmSubscription;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterConfirmationController extends Controller
{
    public function show(string $token, ConfirmSubscription $confirmSubscription): Response
    {
        abort_if(! $confirmSubscription->findValid($token) instanceof NewsletterSubscriber, 404);

        return Inertia::render('public/newsletter/confirm', [
            'token' => $token,
            'seo' => Seo::make(title: 'Confirm subscription — '.Seo::siteName()),
        ]);
    }

    public function store(string $token, ConfirmSubscription $confirmSubscription): RedirectResponse
    {
        $subscriber = $confirmSubscription->handle($token);
        abort_if(! $subscriber instanceof NewsletterSubscriber, 404);

        return to_route('newsletter.confirmed')->with([
            'subscription_confirmed' => true,
            'source_content_key' => $subscriber->source_content_key,
        ]);
    }

    public function confirmed(): Response
    {
        return Inertia::render('public/newsletter/confirmed', [
            'subscriptionConfirmed' => session()->pull('subscription_confirmed', false),
            'sourceContentKey' => session()->pull('source_content_key'),
            'seo' => Seo::make(title: 'Subscription confirmed — '.Seo::siteName()),
        ]);
    }
}
