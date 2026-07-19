<x-mail::message>
@if ($comment->status === \App\Domain\Community\Enums\CommentStatus::Approved)
# Your comment is now public

Your comment passed moderation and is visible on the article.
@else
# Your comment was not approved

Your comment will remain private.

@if ($comment->moderation_reason)
Reason: {{ $comment->moderation_reason }}
@endif
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
