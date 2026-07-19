<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactSubmissionRequest;
use App\Models\ContactSubmission;
use App\Support\Seo;
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

    public function store(StoreContactSubmissionRequest $request): RedirectResponse
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

        ContactSubmission::create([
            ...$request->validated(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thanks! Your message has been sent.')]);

        return back();
    }
}
