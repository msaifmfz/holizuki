<?php

declare(strict_types=1);

namespace App\Http\Public\Controllers;

use App\Domain\Inbox\Actions\ReceiveContactSubmission;
use App\Domain\Reading\Support\Seo;
use App\Http\Controller;
use App\Http\Public\Requests\StoreContactSubmissionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/contact', [
            'seo' => Seo::make(
                title: 'Contact — '.Seo::siteName(),
                canonical: route('public.contact.create'),
            ),
        ]);
    }

    public function store(StoreContactSubmissionRequest $request, ReceiveContactSubmission $receiveContactSubmission): RedirectResponse
    {
        /*
         * The "company" field is a honeypot: it is hidden from humans, so a
         * filled value means a bot. Return the normal success response without
         * storing anything, so bots get no signal that they were caught.
         * filled() tolerates non-string input, unlike string().
         */
        if ($request->filled('company')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your message has been sent.')]);

            return back();
        }

        $receiveContactSubmission->handle([
            ...$request->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your message has been sent.')]);

        return back();
    }
}
