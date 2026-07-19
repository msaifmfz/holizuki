import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Hash } from 'lucide-react';
import EmptyState from '@/components/public/empty-state';
import { show as categoryShow } from '@/routes/public/categories';
import { show as tagShow } from '@/routes/public/tags';
import type { PublicCategory, PublicTag } from '@/types';

export default function Topics({
    categories,
    tags,
}: {
    categories: PublicCategory[];
    tags: PublicTag[];
}) {
    return (
        <>
            <Head title="Topics" />
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <header className="grid gap-4 border-b pb-10">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Explore
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        Topics
                    </h1>
                    <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                        Browse the ideas, tools, and themes covered across the
                        blog.
                    </p>
                </header>

                {categories.length > 0 ? (
                    <section aria-labelledby="categories-heading">
                        <h2
                            id="categories-heading"
                            className="mb-6 font-display text-2xl font-semibold"
                        >
                            Categories
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {categories.map((category) => (
                                <Link
                                    key={category.slug}
                                    href={categoryShow(category.slug)}
                                    className="group grid gap-3 rounded-xl border p-5 transition-colors hover:bg-muted/40"
                                    prefetch
                                >
                                    <span className="flex items-start justify-between gap-3">
                                        <span className="font-display text-xl font-semibold">
                                            {category.name}
                                        </span>
                                        <ArrowRight
                                            className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                                            aria-hidden
                                        />
                                    </span>
                                    {category.description && (
                                        <span className="text-sm leading-6 text-muted-foreground">
                                            {category.description}
                                        </span>
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        {category.posts_count}{' '}
                                        {category.posts_count === 1
                                            ? 'post'
                                            : 'posts'}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                ) : (
                    <EmptyState
                        title="No topics yet"
                        description="Categories will appear here once posts are published."
                    />
                )}

                {tags.length > 0 && (
                    <section
                        className="border-t pt-10"
                        aria-labelledby="tags-heading"
                    >
                        <h2
                            id="tags-heading"
                            className="mb-6 font-display text-2xl font-semibold"
                        >
                            Tags
                        </h2>
                        <div className="flex flex-wrap gap-2.5">
                            {tags.map((tag) => (
                                <Link
                                    key={tag.slug}
                                    href={tagShow(tag.slug)}
                                    className="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm transition-colors hover:bg-muted"
                                    prefetch
                                >
                                    <Hash
                                        className="size-3.5 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <span>{tag.name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {tag.posts_count}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
