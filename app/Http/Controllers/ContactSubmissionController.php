<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContactSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactSubmissionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ContactSubmission::class);

        return Inertia::render('contact-submissions/index', [
            'submissions' => ContactSubmission::query()
                ->latest('id')
                ->paginate(20)
                ->through(fn (ContactSubmission $submission): array => [
                    'id' => $submission->id,
                    'name' => $submission->name,
                    'email' => $submission->email,
                    'subject' => $submission->subject,
                    'message' => $submission->message,
                    'read_at' => $submission->read_at?->toISOString(),
                    'created_at' => $submission->created_at?->toISOString(),
                ]),
            'unreadCount' => ContactSubmission::whereNull('read_at')->count(),
        ]);
    }

    public function markRead(ContactSubmission $contactSubmission): RedirectResponse
    {
        Gate::authorize('update', $contactSubmission);

        if (! $contactSubmission->isRead()) {
            $contactSubmission->update(['read_at' => now()]);
        }

        return back();
    }

    public function destroy(ContactSubmission $contactSubmission): RedirectResponse
    {
        Gate::authorize('delete', $contactSubmission);

        $contactSubmission->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Message deleted.')]);

        return back();
    }
}
