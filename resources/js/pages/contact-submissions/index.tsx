import { Head, router } from '@inertiajs/react';
import { ChevronDown, MailOpen, Trash2 } from 'lucide-react';
import {
    destroy,
    index,
    markRead,
} from '@/actions/App/Http/Admin/Controllers/ContactSubmissionController';
import Pagination from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { formatDate } from '@/lib/post-editor';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

type Submission = {
    id: number;
    name: string;
    email: string;
    subject: string | null;
    message: string;
    read_at: string | null;
    created_at: string | null;
};

type Props = {
    submissions: Paginated<Submission>;
    unreadCount: number;
};

export default function ContactSubmissionsIndex({
    submissions,
    unreadCount,
}: Props) {
    const openSubmission = (submission: Submission, open: boolean) => {
        if (open && submission.read_at === null) {
            router.visit(markRead(submission.id), { preserveScroll: true });
        }
    };

    const removeSubmission = (submission: Submission) => {
        if (window.confirm(`Delete the message from ${submission.name}?`)) {
            router.visit(destroy(submission.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Inbox" />
            <div className="mx-auto grid w-full max-w-4xl gap-6 p-4 lg:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inbox
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Messages sent through the public contact form.
                        </p>
                    </div>
                    {unreadCount > 0 && <Badge>{unreadCount} unread </Badge>}
                </div>

                <Card>
                    <CardContent className="p-0">
                        {submissions.data.length === 0 ? (
                            <div className="grid min-h-48 place-items-center p-8 text-center">
                                <div>
                                    <p className="font-medium">
                                        No messages yet
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Contact form submissions will appear
                                        here.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {submissions.data.map((submission) => (
                                    <Collapsible
                                        key={submission.id}
                                        onOpenChange={(open) =>
                                            openSubmission(submission, open)
                                        }
                                    >
                                        <div
                                            className={cn(
                                                'flex flex-col gap-2 p-4',
                                                submission.read_at === null &&
                                                    'bg-muted/40',
                                            )}
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {submission.name}
                                                        </span>
                                                        {submission.read_at ===
                                                            null && (
                                                            <Badge variant="secondary">
                                                                New
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="truncate text-sm text-muted-foreground">
                                                        {submission.subject ??
                                                            'No subject'}{' '}
                                                        —{' '}
                                                        {formatDate(
                                                            submission.created_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex shrink-0 items-center gap-1">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <a
                                                            href={`mailto:${submission.email}`}
                                                        >
                                                            <MailOpen />
                                                            Reply
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            removeSubmission(
                                                                submission,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        <span className="sr-only">
                                                            Delete message from{' '}
                                                            {submission.name}
                                                        </span>
                                                    </Button>
                                                    <CollapsibleTrigger asChild>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            aria-label={`Toggle message from ${submission.name}`}
                                                        >
                                                            <ChevronDown />
                                                        </Button>
                                                    </CollapsibleTrigger>
                                                </div>
                                            </div>
                                            <CollapsibleContent>
                                                <div className="grid gap-2 rounded-md border bg-background p-3 text-sm">
                                                    <p className="text-muted-foreground">
                                                        {submission.email}
                                                    </p>
                                                    <p className="whitespace-pre-wrap">
                                                        {submission.message}
                                                    </p>
                                                </div>
                                            </CollapsibleContent>
                                        </div>
                                    </Collapsible>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Pagination paginator={submissions} label="Inbox pages" />
            </div>
        </>
    );
}

ContactSubmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Inbox', href: index() }],
};
