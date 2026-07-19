<?php

declare(strict_types=1);

namespace App\Http\Admin\Controllers;

use App\Domain\Community\Actions\StartSubscription;
use App\Domain\Community\Enums\SubscriberStatus;
use App\Domain\Community\Models\NewsletterSubscriber;
use App\Domain\Community\Support\SubscriberIdentity;
use App\Http\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunitySubscriberController extends Controller
{
    public function __construct(private readonly SubscriberIdentity $identity) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);
        $email = $request->string('email')->trim()->lower()->toString();
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();

        $subscribers = NewsletterSubscriber::query()
            ->when($email !== '', fn (Builder $query): Builder => $query->where('email_hash', $this->identity->hash($email)))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($source !== '', fn (Builder $query): Builder => $query->where('source_location', $source))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (NewsletterSubscriber $subscriber): array => $this->subscriberData($subscriber));

        return Inertia::render('community/subscribers/index', [
            'subscribers' => $subscribers,
            'filters' => ['email' => $email, 'status' => $status, 'source' => $source],
        ]);
    }

    public function resend(NewsletterSubscriber $subscriber, StartSubscription $startSubscription): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        abort_if($subscriber->email === null || $subscriber->status !== SubscriberStatus::Pending, 422);

        $startSubscription->handle(
            email: $subscriber->email,
            sourcePost: $subscriber->sourcePost,
            sourceMethod: $subscriber->source_method,
            sourceLocation: $subscriber->source_location,
            consentVersion: $subscriber->consent_version,
            forceResend: true,
        );

        return back()->with('success', 'Confirmation email queued.');
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): RedirectResponse
    {
        Gate::authorize('update', $subscriber);
        $tokenHash = $subscriber->unsubscribe_token_hash;
        abort_if($tokenHash === null, 422);

        $subscriber->forceFill([
            'email' => null,
            'status' => SubscriberStatus::Unsubscribed,
            'confirmation_token_hash' => null,
            'unsubscribe_token_hash' => null,
            'confirmation_expires_at' => null,
            'unsubscribed_at' => now(),
            'erased_at' => now(),
        ])->save();

        return back()->with('success', 'Subscriber unsubscribed.');
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('viewAny', NewsletterSubscriber::class);

        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            if (! is_resource($stream)) {
                throw new RuntimeException('The subscriber export stream could not be opened.');
            }
            fputcsv($stream, ['email', 'confirmed_at', 'source_method', 'source_location', 'source_content_key'], escape: '\\');

            NewsletterSubscriber::query()
                ->where('status', SubscriberStatus::Confirmed)
                ->whereNotNull('email')
                ->orderBy('id')
                ->lazyById()
                ->each(function (NewsletterSubscriber $subscriber) use ($stream): void {
                    fputcsv($stream, [
                        $subscriber->email,
                        $subscriber->confirmed_at?->toISOString(),
                        $subscriber->source_method,
                        $subscriber->source_location,
                        $subscriber->source_content_key,
                    ],
                        escape: '\\');
                });

            fclose($stream);
        }, 'subscribers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function subscriberData(NewsletterSubscriber $subscriber): array
    {
        return [
            'id' => $subscriber->id,
            'email' => $subscriber->email,
            'status' => $subscriber->status->value,
            'source_method' => $subscriber->source_method,
            'source_location' => $subscriber->source_location,
            'source_content_key' => $subscriber->source_content_key,
            'confirmation_sent_at' => $subscriber->confirmation_sent_at?->toISOString(),
            'confirmed_at' => $subscriber->confirmed_at?->toISOString(),
        ];
    }
}
