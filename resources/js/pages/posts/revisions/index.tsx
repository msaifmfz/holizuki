import { Head, Link, router, setLayoutProps, useHttp } from '@inertiajs/react';
import { ArrowLeft, Eye, RotateCcw } from 'lucide-react';
import {
    edit,
    index as postsIndex,
} from '@/actions/App/Http/Controllers/PostController';
import {
    index,
    restore,
    show,
} from '@/actions/App/Http/Controllers/PostRevisionController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate } from '@/lib/post-editor';
import type { Paginated, PostRevisionSummary } from '@/types';

type Props = {
    post: { id: number; title: string; lock_version: number };
    revisions: Paginated<PostRevisionSummary>;
};

export default function RevisionsIndex({ post, revisions }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Posts', href: postsIndex() },
            { title: 'Revisions', href: index(post.id) },
        ],
    });

    const restoreRequest = useHttp<
        { lock_version: number },
        { lock_version: number }
    >({ lock_version: post.lock_version });

    const restoreRevision = async (revisionId: number) => {
        if (
            !window.confirm(
                'Restore this revision? Current publication status will not change.',
            )
        ) {
            return;
        }

        restoreRequest.transform(() => ({ lock_version: post.lock_version }));

        try {
            await restoreRequest.post(
                restore.url({ post: post.id, revision: revisionId }),
            );
            router.visit(edit(post.id));
        } catch {
            router.reload();
        }
    };

    return (
        <>
            <Head title={`Revisions: ${post.title}`} />
            <div className="mx-auto grid w-full max-w-5xl gap-6 p-4 lg:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Revision history
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {post.title}
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={edit(post.id)}>
                            <ArrowLeft />
                            Back to editor
                        </Link>
                    </Button>
                </div>
                <Card>
                    <CardContent className="divide-y p-0">
                        {revisions.data.length === 0 ? (
                            <p className="p-8 text-center text-muted-foreground">
                                No revisions yet. Manual saves and publishing
                                events appear here.
                            </p>
                        ) : (
                            revisions.data.map((revision) => (
                                <article
                                    key={revision.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                Revision{' '}
                                                {revision.revision_number}
                                            </span>
                                            <Badge variant="outline">
                                                {eventLabel(revision.event)}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            {revision.editor} ·{' '}
                                            {formatDate(revision.created_at)}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={show({
                                                    post: post.id,
                                                    revision: revision.id,
                                                })}
                                            >
                                                <Eye />
                                                Preview
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            disabled={restoreRequest.processing}
                                            onClick={() =>
                                                restoreRevision(revision.id)
                                            }
                                        >
                                            <RotateCcw />
                                            Restore
                                        </Button>
                                    </div>
                                </article>
                            ))
                        )}
                    </CardContent>
                </Card>
                {revisions.last_page > 1 && (
                    <nav className="flex justify-center gap-1">
                        {revisions.links.map(
                            (link) =>
                                link.url && (
                                    <Button
                                        key={link.label}
                                        asChild
                                        size="sm"
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                    >
                                        <Link
                                            href={link.url}
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Button>
                                ),
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}

function eventLabel(value: string): string {
    return value
        .replaceAll('_', ' ')
        .replace(/^./, (letter) => letter.toUpperCase());
}
