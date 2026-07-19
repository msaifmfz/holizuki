<?php

declare(strict_types=1);

namespace App\Domain\Community\Mail;

use App\Domain\Community\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriberConfirmationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public NewsletterSubscriber $subscriber,
        public string $confirmationToken,
        public string $unsubscribeToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your newsletter subscription');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.community.confirm-subscription',
            with: [
                'confirmationUrl' => route('newsletter.confirm.show', $this->confirmationToken),
                'unsubscribeUrl' => route('newsletter.unsubscribe.show', $this->unsubscribeToken),
            ],
        );
    }
}
