import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    index,
    update,
} from '@/actions/App/Http/Admin/Controllers/CommunityCommentController';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Paginated } from '@/types';

type CommentRow = {
    id: number;
    body: string;
    status: string;
    reader_name: string;
    post: { title: string; slug: string };
    submitted_at: string;
    reason: string | null;
};

export default function CommunityComments({
    comments,
    filters,
    counts,
}: {
    comments: Paginated<CommentRow>;
    filters: { status: string };
    counts: Record<string, number>;
}) {
    const [status, setStatus] = useState(filters.status || 'all');

    const applyStatus = (value: string) => {
        setStatus(value);
        router.get(
            index.url({
                query: { status: value === 'all' ? undefined : value },
            }),
            {},
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Comments" />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 lg:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Community comments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Approve, reject with an optional reason, or delete.
                            Reader content is never rewritten.
                        </p>
                    </div>
                    <Select value={status} onValueChange={applyStatus}>
                        <SelectTrigger
                            className="w-48"
                            aria-label="Comment status"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All comments</SelectItem>
                            <SelectItem value="pending">
                                Pending ({counts.pending ?? 0})
                            </SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="deleted">Deleted</SelectItem>
                        </SelectContent>
                    </Select>
                </header>
                <div className="grid gap-4">
                    {comments.data.map((comment) => (
                        <Card key={comment.id}>
                            <CardContent className="grid gap-4 pt-6">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-medium">
                                            {comment.reader_name} on{' '}
                                            {comment.post.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                comment.submitted_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {comment.status}
                                    </Badge>
                                </div>
                                <p className="text-sm leading-6 whitespace-pre-wrap">
                                    {comment.body || 'Comment body erased'}
                                </p>
                                {comment.reason && (
                                    <p className="text-sm text-muted-foreground">
                                        Moderator reason: {comment.reason}
                                    </p>
                                )}
                                {comment.status !== 'deleted' && (
                                    <Form
                                        {...update.form(comment.id)}
                                        className="grid gap-3 rounded-lg border bg-muted/20 p-3"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <Textarea
                                                    name="reason"
                                                    maxLength={1000}
                                                    placeholder="Optional reason included in the rejection email"
                                                    aria-label="Moderation reason"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Readers are emailed on both
                                                    approval and rejection.
                                                </p>
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        name="status"
                                                        value="approved"
                                                        disabled={processing}
                                                    >
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        name="status"
                                                        value="rejected"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Reject
                                                    </Button>
                                                    <Button
                                                        name="status"
                                                        value="deleted"
                                                        variant="destructive"
                                                        disabled={processing}
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </>
                                        )}
                                    </Form>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                    {comments.data.length === 0 && (
                        <Card>
                            <CardContent className="py-12 text-center text-sm text-muted-foreground">
                                No comments match this filter.
                            </CardContent>
                        </Card>
                    )}
                </div>
                <Pagination paginator={comments} label="Comment pages" />
            </div>
        </>
    );
}

CommunityComments.layout = {
    breadcrumbs: [{ title: 'Comments', href: index() }],
};
