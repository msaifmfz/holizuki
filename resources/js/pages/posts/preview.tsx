import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, History } from 'lucide-react';
import { edit } from '@/actions/App/Http/Controllers/PostController';
import { index as revisionsIndex } from '@/actions/App/Http/Controllers/PostRevisionController';
import RichTextEditor from '@/components/rich-text-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/post-editor';
import type { PostPreviewData, PostRevisionSummary } from '@/types';

export default function PostPreview({
    post,
    revision,
}: {
    post: PostPreviewData;
    revision: PostRevisionSummary | null;
}) {
    return (
        <>
            <Head title={`Preview: ${post.title}`} />
            <div className="border-b bg-muted/30">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary">
                            {revision
                                ? `Revision ${revision.revision_number}`
                                : 'Private preview'}
                        </Badge>
                        <span className="text-sm text-muted-foreground">
                            Only administrators can see this page.
                        </span>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild size="sm" variant="outline">
                            <Link href={edit(post.id)}>
                                <ArrowLeft />
                                Back to editor
                            </Link>
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link href={revisionsIndex(post.id)}>
                                <History />
                                History
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
            <article className="mx-auto w-full max-w-4xl px-4 py-10 sm:py-16">
                <header className="mb-10 grid gap-5 text-center">
                    <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        {post.title}
                    </h1>
                    {post.excerpt && (
                        <p className="mx-auto max-w-2xl text-lg leading-8 text-muted-foreground">
                            {post.excerpt}
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Last updated {formatDate(post.updated_at, 'long')}
                    </p>
                </header>
                {post.featured_image_url && (
                    <img
                        src={post.featured_image_url}
                        alt={post.featured_image_alt ?? ''}
                        className="mb-10 aspect-video w-full rounded-xl border object-cover shadow-sm"
                    />
                )}
                <RichTextEditor
                    value={post.body}
                    readOnly
                    className="border-0"
                />
            </article>
        </>
    );
}
