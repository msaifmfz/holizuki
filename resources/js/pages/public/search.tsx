import { Head, router } from '@inertiajs/react';
import { Search as SearchIcon } from 'lucide-react';
import { useState } from 'react';

import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PostList from '@/components/public/post-list';
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
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <header className="grid gap-5 border-t border-border pt-5">
                    <p className="eyebrow text-brand-accent">Search</p>
                    <h1 className="display-lg">Find a post</h1>

                    {/* Field and button share one hairline box, matching the
                        newsletter form — the only two inputs on the site. */}
                    <form
                        onSubmit={submit}
                        className="flex max-w-xl border border-border"
                        role="search"
                    >
                        <div className="relative flex-1">
                            <SearchIcon
                                className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden
                            />
                            <Input
                                type="search"
                                value={term}
                                onChange={(event) =>
                                    setTerm(event.target.value)
                                }
                                maxLength={100}
                                className="h-11 rounded-none border-0 pl-10 font-body shadow-none"
                                placeholder="Try a city, a country, or a hotel name"
                                aria-label="Search posts"
                            />
                        </div>
                        <Button
                            type="submit"
                            className="micro-cta h-11 rounded-none px-5"
                        >
                            Search
                        </Button>
                    </form>

                    {query !== '' && (
                        <p className="eyebrow text-muted-foreground">
                            {posts.total}{' '}
                            {posts.total === 1 ? 'result' : 'results'} for
                            &ldquo;{query}&rdquo;
                        </p>
                    )}
                </header>

                {query === '' ? (
                    <EmptyState
                        title="Where do you want to go?"
                        description="Type a place, a hotel, or a writer above to search every published trip."
                    />
                ) : posts.data.length > 0 ? (
                    <div className="grid gap-14">
                        <PostList
                            posts={posts.data}
                            contentSource="search"
                            contentLocation="results"
                        />
                        <Pagination paginator={posts} label="Search results" />
                    </div>
                ) : (
                    <EmptyState
                        title="Nothing down that road"
                        description={`We haven’t written about “${query}” yet. Try a shorter or broader search.`}
                    />
                )}
            </div>
        </>
    );
}
