<?php

declare(strict_types=1);

namespace App\Domain\Community\Mail;

use App\Domain\Community\Enums\CommentStatus;
use App\Domain\Community\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommentModeratedMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->comment->status === CommentStatus::Approved
            ? 'Your comment was approved'
            : 'Update about your comment');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.community.comment-moderated');
    }
}
