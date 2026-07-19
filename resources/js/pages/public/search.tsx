import { Head, router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/public';
import type { Paginated, PublicPostCard } from '@/types';

type Props = {
    query: string;
    posts: Paginated<PublicPostCard>;
};

export default function SearchPage({ query, posts }: Props) {
    const [term, setTerm] = useState(query);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            search.url({ query: { q: term.trim() } }),
            {},
            { preserveState: true },
        );
    };

    return (
        <>
            <Head title="Search" />
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12">
                <header className="grid gap-4 border-b pb-10">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Search
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                        Find a post
                    </h1>
                    <form
                        onSubmit={submit}
                        className="flex max-w-xl gap-2"
                        role="search"
                    >
                        <div className="relative flex-1">
                            <SearchIcon
                                className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                value={term}
                                onChange={(event) =>
                                    setTerm(event.target.value)
                                }
                                maxLength={100}
                                className="pl-9"
                                placeholder="Search posts, topics, tags, and authors"
                                aria-label="Search posts"
                            />
                        </div>
                        <Button type="submit">Search</Button>
                    </form>
                    {query !== '' && (
                        <p className="text-sm text-muted-foreground">
                            {posts.total}{' '}
                            {posts.total === 1 ? 'result' : 'results'} for “
                            {query}”
                        </p>
                    )}
                </header>

                {query === '' ? (
                    <EmptyState
                        title="Search the blog"
                        description="Type a few words above to search every published post, topic, tag, and author."
                    />
                ) : posts.data.length > 0 ? (
                    <>
                        <PostGrid posts={posts.data} />
                        <Pagination paginator={posts} label="Search results" />
                    </>
                ) : (
                    <EmptyState
                        title="No results"
                        description={`Nothing matched “${query}”. Try different or shorter keywords.`}
                    />
                )}
            </div>
        </>
    );
}
