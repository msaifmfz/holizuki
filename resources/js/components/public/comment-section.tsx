import { Form, InfiniteScroll, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Public/Controllers/CommentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { formatCompactDate } from '@/lib/public-date';
import { login, register } from '@/routes';

export type PublicComment = {
    id: number;
    body: string;
    display_name: string;
    is_author: boolean;
    submitted_at: string;
    can_edit: boolean;
};

export type PrivateComment = {
    id: number;
    body: string;
    status: 'pending' | 'rejected' | 'approved';
    reason: string | null;
    submitted_at: string;
    can_edit: boolean;
};

type CommentPaginator = {
    data: PublicComment[];
    [key: string]: unknown;
};

type Props = {
    postSlug: string;
    comments: CommentPaginator;
    myComments: PrivateComment[];
};

/**
 * Comments, on the same vocabulary as the rest of the article.
 *
 * Was a stack of rounded tinted panels and cards — an app surface bolted to the
 * end of a page. Now the section is ruled off like every other block on the
 * article, each comment is a hairline-separated row rather than a card, and the
 * notices are ruled rather than boxed.
 */
export default function CommentSection({
    postSlug,
    comments,
    myComments,
}: Props) {
    const { auth } = usePage().props;
    const returnTo = `/posts/${postSlug}#comments`;
    const isVerifiedReader =
        auth.user?.role === 'reader' && auth.user.email_verified_at !== null;
    const isAuthor =
        auth.user?.role === 'administrator' &&
        auth.user.email_verified_at !== null;

    return (
        // The class name is load-bearing: print CSS hides it by name.
        <section
            id="comments"
            className="post-comments mt-14 grid gap-6 border-t border-border pt-8"
            aria-labelledby="comments-title"
        >
            <div className="grid gap-2.5">
                <p className="eyebrow text-brand-accent">Comments</p>
                <h2 id="comments-title" className="display-md">
                    Join the conversation
                </h2>
                <p className="max-w-xl font-body leading-relaxed text-muted-foreground">
                    Been here? Got a better hotel? Comments are read before they
                    go up — replies from us appear straight away.
                </p>
            </div>

            {!auth.user && (
                <p className="font-body leading-relaxed text-muted-foreground">
                    <Link
                        href={login({ query: { return_to: returnTo } })}
                        className="font-medium text-primary underline decoration-primary/40 underline-offset-4"
                    >
                        Sign in
                    </Link>{' '}
                    or{' '}
                    <Link
                        href={register({ query: { return_to: returnTo } })}
                        className="font-medium text-primary underline decoration-primary/40 underline-offset-4"
                    >
                        create a reader account
                    </Link>{' '}
                    to join the conversation.
                </p>
            )}

            {auth.user?.role === 'reader' &&
                auth.user.email_verified_at === null && (
                    <p className="border-l-2 border-warning/60 pl-4 font-body leading-relaxed text-warning">
                        Verify your email address before submitting a comment.
                    </p>
                )}

            {(isVerifiedReader || isAuthor) && (
                <Form {...store.form(postSlug)} resetOnSuccess={['body']}>
                    {({ processing, errors, wasSuccessful }) => (
                        <div className="grid max-w-2xl gap-3">
                            <Textarea
                                name="body"
                                required
                                maxLength={2000}
                                rows={5}
                                aria-label="Comment"
                                placeholder="Tell us where you’d go back to…"
                                className="rounded-none border-border font-body shadow-none"
                            />
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="eyebrow text-muted-foreground">
                                    2,000 characters max, links stay plain text
                                </p>
                                <Button
                                    type="submit"
                                    data-testid="submit-comment"
                                    disabled={processing}
                                    className="micro-cta h-10 rounded-none px-5"
                                >
                                    {isAuthor
                                        ? 'Post reply'
                                        : 'Submit for review'}
                                </Button>
                            </div>
                            <InputError message={errors.body} />
                            {wasSuccessful && (
                                <p className="text-sm font-medium text-success">
                                    {isAuthor
                                        ? 'Your reply is published.'
                                        : 'Your comment is pending moderation.'}
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            )}

            {myComments.length > 0 && (
                <div className="grid max-w-2xl gap-4">
                    <h3 className="rule-label">Your private comments</h3>
                    {myComments.map((comment) => (
                        <EditableComment key={comment.id} comment={comment} />
                    ))}
                </div>
            )}

            {comments.data.length > 0 && (
                <InfiniteScroll data="comments">
                    <ol className="grid border-t border-border">
                        {comments.data.map((comment) => (
                            <li
                                key={comment.id}
                                className="grid gap-2 border-b border-border py-5"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <p className="flex items-baseline gap-2.5">
                                        <span className="font-display font-medium [font-stretch:100%]">
                                            {comment.display_name}
                                        </span>
                                        {comment.is_author && (
                                            <span className="eyebrow border border-border px-1.5 py-0.5 text-brand-accent">
                                                Author
                                            </span>
                                        )}
                                    </p>
                                    <time
                                        className="eyebrow meta-num text-muted-foreground"
                                        dateTime={comment.submitted_at}
                                    >
                                        {formatCompactDate(
                                            comment.submitted_at,
                                        )}
                                    </time>
                                </div>
                                <p className="font-body leading-relaxed whitespace-pre-wrap">
                                    {comment.body}
                                </p>
                                {comment.can_edit && (
                                    <EditableComment
                                        comment={{
                                            ...comment,
                                            status: 'approved',
                                            reason: null,
                                        }}
                                        showBody={false}
                                    />
                                )}
                            </li>
                        ))}
                    </ol>
                </InfiniteScroll>
            )}
        </section>
    );
}

function EditableComment({
    comment,
    showBody = true,
}: {
    comment: PrivateComment;
    showBody?: boolean;
}) {
    const [editing, setEditing] = useState(false);

    return (
        <div className="grid gap-2 border-l-2 border-border pl-4">
            <div className="flex items-center justify-between gap-3">
                <span className="eyebrow text-muted-foreground">
                    {comment.status}
                </span>
                {comment.can_edit && (
                    <div className="flex gap-4">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing((value) => !value)}
                            className="eyebrow h-auto rounded-none px-0 text-muted-foreground underline-offset-4 hover:bg-transparent hover:text-primary hover:underline"
                        >
                            Edit
                        </Button>
                        <Form {...destroy.form(comment.id)}>
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="ghost"
                                    size="sm"
                                    disabled={processing}
                                    className="eyebrow h-auto rounded-none px-0 text-muted-foreground underline-offset-4 hover:bg-transparent hover:text-destructive hover:underline"
                                >
                                    Delete
                                </Button>
                            )}
                        </Form>
                    </div>
                )}
            </div>
            {!editing && showBody && (
                <p className="font-body leading-relaxed whitespace-pre-wrap">
                    {comment.body}
                </p>
            )}
            {comment.reason && (
                <p className="font-body text-muted-foreground">
                    Moderator note: {comment.reason}
                </p>
            )}
            {editing && (
                <Form {...update.form(comment.id)}>
                    {({ processing, errors }) => (
                        <div className="grid gap-2">
                            <Textarea
                                name="body"
                                defaultValue={comment.body}
                                required
                                maxLength={2000}
                                className="rounded-none border-border font-body shadow-none"
                            />
                            <InputError message={errors.body} />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
                                className="micro-cta h-9 w-fit rounded-none px-4"
                            >
                                Save and resubmit
                            </Button>
                        </div>
                    )}
                </Form>
            )}
        </div>
    );
}
