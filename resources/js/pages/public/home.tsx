import { Head, Link } from '@inertiajs/react';
import { MoveRight } from 'lucide-react';
import Pagination from '@/components/pagination';
import AuthorByline from '@/components/public/author-byline';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import type { Paginated, PublicPostCard } from '@/types';

type Props = {
    featured: PublicPostCard | null;
    posts: Paginated<PublicPostCard>;
};

export default function Home({ featured, posts }: Props) {
    const showHero = featured !== null && posts.current_page === 1;

    return (
        <>
            <Head title="Home" />
            <div className="mx-auto w-full max-w-6xl px-4">
                {showHero && <FeaturedHero post={featured} />}

                <section className="py-12">
                    <h2 className="mb-8 flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        {showHero ? 'More writing' : 'Latest writing'}
                    </h2>

                    {posts.data.length > 0 ? (
                        <div className="grid gap-10">
                            <PostGrid posts={posts.data} />
                            <Pagination paginator={posts} label="Blog pages" />
                        </div>
                    ) : (
                        !showHero && (
                            <EmptyState
                                title="Nothing published yet"
                                description="New writing lands here as soon as it's published. Check back soon."
                            />
                        )
                    )}
                </section>
            </div>
        </>
    );
}

function FeaturedHero({ post }: { post: PublicPostCard }) {
    return (
        <section className="relative isolate overflow-visible border-b py-12 sm:py-16 lg:py-20">
            <div
                aria-hidden
                className="moon-glow absolute -top-40 -right-24 -z-10 size-[30rem] rounded-full"
            />
            <div className="grid items-center gap-10 lg:grid-cols-[1.1fr_1fr]">
                <div className="grid gap-5">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        {post.category ? (
                            <Link
                                href={categoryShow(post.category.slug)}
                                className="transition-colors hover:text-foreground"
                            >
                                {post.category.name}
                            </Link>
                        ) : (
                            'Featured'
                        )}
                    </p>
                    <h1 className="font-display text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        <Link href={postShow(post.slug)} prefetch>
                            {post.title}
                        </Link>
                    </h1>
                    {post.excerpt && (
                        <p className="max-w-prose text-lg leading-8 text-muted-foreground">
                            {post.excerpt}
                        </p>
                    )}
                    <div className="flex flex-wrap items-center gap-5">
                        <AuthorByline
                            author={post.author}
                            date={post.published_at}
                            size="md"
                        />
                        <Link
                            href={postShow(post.slug)}
                            className="flex items-center gap-1.5 text-sm font-medium hover:underline hover:underline-offset-4"
                            prefetch
                        >
                            Read the post
                            <MoveRight className="size-4" aria-hidden />
                        </Link>
                    </div>
                </div>
                {post.featured_image_url && (
                    <Link
                        href={postShow(post.slug)}
                        tabIndex={-1}
                        aria-hidden
                        prefetch
                    >
                        <img
                            src={post.featured_image_url}
                            alt={post.featured_image_alt ?? ''}
                            className="aspect-[4/3] w-full rounded-2xl border object-cover shadow-sm"
                        />
                    </Link>
                )}
            </div>
        </section>
    );
}
