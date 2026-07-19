<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Community\Actions\Unsubscribe;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterUnsubscribeController extends Controller
{
    public function show(string $token, Unsubscribe $unsubscribe): Response
    {
        abort_if(! $unsubscribe->find($token) instanceof NewsletterSubscriber, 404);

        return Inertia::render('public/newsletter/unsubscribe', [
            'token' => $token,
            'seo' => Seo::make(title: 'Unsubscribe — '.Seo::siteName()),
        ]);
    }

    public function store(string $token, Unsubscribe $unsubscribe): RedirectResponse
    {
        abort_unless($unsubscribe->handle($token), 404);

        return to_route('newsletter.unsubscribed');
    }

    public function unsubscribed(): Response
    {
        return Inertia::render('public/newsletter/unsubscribed', [
            'seo' => Seo::make(title: 'Unsubscribed — '.Seo::siteName()),
        ]);
    }
}
