import { Form, InfiniteScroll, Link, usePage } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Public/Controllers/CommentController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
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
        <section
            id="comments"
            className="mt-12 grid gap-6"
            aria-labelledby="comments-title"
        >
            <div className="grid gap-1">
                <h2
                    id="comments-title"
                    className="flex items-center gap-2 font-display text-2xl font-semibold"
                >
                    <MessageCircle className="size-5" aria-hidden />
                    Comments
                </h2>
                <p className="text-sm text-muted-foreground">
                    Reader comments are reviewed before they appear publicly.
                    Author replies appear immediately.
                </p>
            </div>

            {!auth.user && (
                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                    <Link
                        href={login({ query: { return_to: returnTo } })}
                        className="font-medium underline underline-offset-4"
                    >
                        Sign in
                    </Link>{' '}
                    or{' '}
                    <Link
                        href={register({ query: { return_to: returnTo } })}
                        className="font-medium underline underline-offset-4"
                    >
                        create a reader account
                    </Link>{' '}
                    to join the conversation.
                </div>
            )}

            {auth.user?.role === 'reader' &&
                auth.user.email_verified_at === null && (
                    <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100">
                        Verify your email address before submitting a comment.
                    </div>
                )}

            {(isVerifiedReader || isAuthor) && (
                <Form {...store.form(postSlug)} resetOnSuccess={['body']}>
                    {({ processing, errors, wasSuccessful }) => (
                        <div className="grid gap-3">
                            <Textarea
                                name="body"
                                required
                                maxLength={2000}
                                rows={5}
                                aria-label="Comment"
                                placeholder="Write a plain-text comment…"
                            />
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-xs text-muted-foreground">
                                    2,000 characters maximum. Links remain plain
                                    text.
                                </p>
                                <Button
                                    type="submit"
                                    data-testid="submit-comment"
                                    disabled={processing}
                                >
                                    {isAuthor
                                        ? 'Post reply'
                                        : 'Submit for review'}
                                </Button>
                            </div>
                            <InputError message={errors.body} />
                            {wasSuccessful && (
                                <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
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
                <div className="grid gap-3 rounded-xl border bg-muted/20 p-4">
                    <h3 className="font-medium">Your private comments</h3>
                    {myComments.map((comment) => (
                        <EditableComment key={comment.id} comment={comment} />
                    ))}
                </div>
            )}

            <InfiniteScroll data="comments">
                <ol className="grid gap-4">
                    {comments.data.map((comment) => (
                        <li key={comment.id} className="rounded-xl border p-5">
                            <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                <p className="flex items-center gap-2 font-medium">
                                    {comment.display_name}
                                    {comment.is_author && (
                                        <Badge variant="secondary">
                                            Author
                                        </Badge>
                                    )}
                                </p>
                                <time
                                    className="text-xs text-muted-foreground"
                                    dateTime={comment.submitted_at}
                                >
                                    {new Date(
                                        comment.submitted_at,
                                    ).toLocaleDateString()}
                                </time>
                            </div>
                            <p className="text-sm leading-6 whitespace-pre-wrap">
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
        <div className="grid gap-2 rounded-lg border bg-background p-3 text-sm">
            <div className="flex items-center justify-between gap-3">
                <span className="font-medium capitalize">{comment.status}</span>
                {comment.can_edit && (
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setEditing((value) => !value)}
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
                                >
                                    Delete
                                </Button>
                            )}
                        </Form>
                    </div>
                )}
            </div>
            {!editing && showBody && (
                <p className="leading-6 whitespace-pre-wrap">{comment.body}</p>
            )}
            {comment.reason && (
                <p className="text-muted-foreground">
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
                            />
                            <InputError message={errors.body} />
                            <Button
                                type="submit"
                                size="sm"
                                disabled={processing}
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
