import { Form, Head, Link, router } from '@inertiajs/react';
import { ArchiveRestore, Edit3, FilePlus2, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import {
    destroy,
    edit,
    index,
    store,
} from '@/actions/App/Http/Controllers/PostController';
import {
    forceDestroy,
    index as trashIndex,
    restore,
} from '@/actions/App/Http/Controllers/PostTrashController';
import PostStatusBadge from '@/components/post-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/post-editor';
import type { Paginated, PostSummary } from '@/types';

type Props = {
    posts: Paginated<PostSummary>;
    filters: { search: string; status: string };
    counts: {
        all: number;
        draft: number;
        scheduled: number;
        published: number;
        trash: number;
    };
    trash: boolean;
};

export default function PostsIndex({ posts, filters, counts, trash }: Props) {
    const [search, setSearch] = useState(filters.search);
    const baseRoute = trash ? trashIndex : index;

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            baseRoute.url({
                query: { search, status: filters.status || undefined },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title={trash ? 'Post Trash' : 'Posts'} />
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 lg:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {trash ? 'Post Trash' : 'Posts'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {trash
                                ? 'Restore posts or remove them permanently.'
                                : 'Draft, schedule, and publish your writing.'}
                        </p>
                    </div>
                    {!trash && (
                        <Form {...store.form()}>
                            <Button>
                                <FilePlus2 />
                                New post
                            </Button>
                        </Form>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <FilterLink
                        label="All"
                        count={counts.all}
                        active={!trash && !filters.status}
                        href={index()}
                    />
                    <FilterLink
                        label="Drafts"
                        count={counts.draft}
                        active={filters.status === 'draft'}
                        href={index({ query: { status: 'draft' } })}
                    />
                    <FilterLink
                        label="Scheduled"
                        count={counts.scheduled}
                        active={filters.status === 'scheduled'}
                        href={index({ query: { status: 'scheduled' } })}
                    />
                    <FilterLink
                        label="Published"
                        count={counts.published}
                        active={filters.status === 'published'}
                        href={index({ query: { status: 'published' } })}
                    />
                    <FilterLink
                        label="Trash"
                        count={counts.trash}
                        active={trash}
                        href={trashIndex()}
                    />
                </div>

                <form onSubmit={submitSearch} className="flex max-w-xl gap-2">
                    <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="pl-9"
                            placeholder="Search titles and slugs"
                            aria-label="Search posts"
                        />
                    </div>
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                <Card>
                    <CardContent className="p-0">
                        {posts.data.length === 0 ? (
                            <div className="grid min-h-64 place-items-center p-8 text-center">
                                <div>
                                    <p className="font-medium">
                                        {trash
                                            ? 'Trash is empty'
                                            : 'No posts found'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {trash
                                            ? 'Deleted posts will appear here.'
                                            : 'Create a draft or adjust the filters.'}
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="divide-y">
                                {posts.data.map((post) => (
                                    <article
                                        key={post.id}
                                        className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="truncate font-medium">
                                                    {post.title}
                                                </h2>
                                                <PostStatusBadge
                                                    status={post.status}
                                                />
                                            </div>
                                            <p className="truncate text-sm text-muted-foreground">
                                                /{post.slug}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Updated{' '}
                                                {formatDate(post.updated_at)}
                                                {post.last_editor
                                                    ? ` by ${post.last_editor}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            {trash ? (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.visit(
                                                                restore(
                                                                    post.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <ArchiveRestore />
                                                        Restore
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            window.confirm(
                                                                'Permanently delete this post and all revisions?',
                                                            ) &&
                                                            router.visit(
                                                                forceDestroy(
                                                                    post.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        Delete forever
                                                    </Button>
                                                </>
                                            ) : (
                                                <>
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={edit(post.id)}
                                                        >
                                                            <Edit3 />
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            window.confirm(
                                                                'Move this post to Trash?',
                                                            ) &&
                                                            router.visit(
                                                                destroy(
                                                                    post.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                        <span className="sr-only">
                                                            Move {post.title} to
                                                            Trash
                                                        </span>
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {posts.last_page > 1 && (
                    <nav
                        className="flex flex-wrap justify-center gap-1"
                        aria-label="Post pages"
                    >
                        {posts.links.map(
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
                                            preserveScroll
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

PostsIndex.layout = { breadcrumbs: [{ title: 'Posts', href: index() }] };

function FilterLink({
    label,
    count,
    active,
    href,
}: {
    label: string;
    count: number;
    active: boolean;
    href: ReturnType<typeof index>;
}) {
    return (
        <Button asChild size="sm" variant={active ? 'default' : 'outline'}>
            <Link href={href}>
                {label}
                <Badge variant={active ? 'secondary' : 'outline'}>
                    {count}
                </Badge>
            </Link>
        </Button>
    );
}
