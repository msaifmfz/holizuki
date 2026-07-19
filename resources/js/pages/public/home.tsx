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
    featured: PublicPostCard[];
    popular: PublicPostCard[];
    posts: Paginated<PublicPostCard>;
};

export default function Home({ featured, popular, posts }: Props) {
    const showFeatured = featured.length > 0 && posts.current_page === 1;

    return (
        <>
            <Head title="Home" />
            <div className="mx-auto w-full max-w-6xl px-4">
                {showFeatured && (
                    <section className="border-b py-12 sm:py-16 lg:py-20">
                        <FeaturedHero post={featured[0]} />
                        {featured.length > 1 && (
                            <div className="mt-12 grid gap-8 border-t pt-10 md:grid-cols-2">
                                {featured.slice(1, 3).map((post) => (
                                    <FeaturedSupportingPost
                                        key={post.id}
                                        post={post}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                )}

                {showFeatured && popular.length > 0 && (
                    <section className="border-b py-12">
                        <SectionHeading>Popular this month</SectionHeading>
                        <PostGrid
                            posts={popular}
                            contentSource="recommendation"
                            contentLocation="popular"
                        />
                    </section>
                )}

                <section className="py-12">
                    <SectionHeading>
                        {showFeatured ? 'Recent posts' : 'Latest writing'}
                    </SectionHeading>

                    {posts.data.length > 0 ? (
                        <div className="grid gap-10">
                            <PostGrid
                                posts={posts.data}
                                contentSource="recommendation"
                                contentLocation="recent"
                            />
                            <Pagination paginator={posts} label="Blog pages" />
                        </div>
                    ) : (
                        !showFeatured && (
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
        <div className="relative isolate overflow-visible">
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
                        <Link
                            href={postShow(post.slug)}
                            data-content-key={`post:${post.id}`}
                            data-content-source="recommendation"
                            data-content-location="featured_hero"
                            prefetch
                        >
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
                            readingTime={post.reading_time_minutes}
                        />
                        <Link
                            href={postShow(post.slug)}
                            data-content-key={`post:${post.id}`}
                            data-content-source="recommendation"
                            data-content-location="featured_hero"
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
                        data-content-key={`post:${post.id}`}
                        data-content-source="recommendation"
                        data-content-location="featured_hero"
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
        </div>
    );
}

function FeaturedSupportingPost({ post }: { post: PublicPostCard }) {
    return (
        <article className="group relative grid gap-3 sm:grid-cols-[8rem_1fr] sm:items-center">
            {post.featured_image_url && (
                <img
                    src={post.featured_image_url}
                    alt={post.featured_image_alt ?? ''}
                    className="aspect-video w-full rounded-xl border object-cover sm:aspect-square"
                />
            )}
            <div className="grid gap-2">
                <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
                    Featured
                </p>
                <h2 className="font-display text-2xl leading-snug font-semibold tracking-tight text-balance">
                    <Link
                        href={postShow(post.slug)}
                        data-content-key={`post:${post.id}`}
                        data-content-source="recommendation"
                        data-content-location="featured_supporting"
                        className="group-hover:underline group-hover:underline-offset-4 after:absolute after:inset-0"
                        prefetch
                    >
                        {post.title}
                    </Link>
                </h2>
                <AuthorByline
                    author={post.author}
                    date={post.published_at}
                    readingTime={post.reading_time_minutes}
                />
            </div>
        </article>
    );
}

function SectionHeading({ children }: { children: React.ReactNode }) {
    return (
        <h2 className="mb-8 flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
            <span className="moon-dot" aria-hidden />
            {children}
        </h2>
    );
}
