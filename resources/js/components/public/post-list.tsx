import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import Dateline from '@/components/public/dateline';
import { RevealGroup, RevealItem } from '@/components/public/motion';
import PostImage from '@/components/public/post-image';
import { cn } from '@/lib/utils';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import type { PublicPostCard } from '@/types';

/**
 * The listing shape for the archive, search, and the category / tag / author
 * pages: hairline-separated rows, image left, type right.
 *
 * Replaces the one-size 3-column card grid those five pages shared. A grid of
 * equal cards gives every post the same weight and reads as a directory; rows
 * give the title room to be long, put the excerpt next to it rather than
 * clamped underneath, and let a set of ten scan top to bottom in one column.
 * Budget Traveller stacks its secondary posts exactly this way.
 */
export default function PostList({
    posts,
    contentSource,
    contentLocation,
    className,
}: {
    posts: PublicPostCard[];
    contentSource?: string;
    contentLocation?: string;
    className?: string;
}) {
    return (
        <RevealGroup
            className={cn('grid border-t border-border', className)}
            role="list"
        >
            {posts.map((post) => (
                <PostListRow
                    key={post.id}
                    post={post}
                    contentSource={contentSource}
                    contentLocation={contentLocation}
                />
            ))}
        </RevealGroup>
    );
}

function PostListRow({
    post,
    contentSource,
    contentLocation,
}: {
    post: PublicPostCard;
    contentSource?: string;
    contentLocation?: string;
}) {
    return (
        <RevealItem
            role="listitem"
            className="group relative grid gap-x-6 gap-y-4 border-b border-border py-7 transition-colors hover:bg-card sm:grid-cols-[minmax(0,13rem)_1fr] sm:py-8"
        >
            {/* Hidden on narrow screens: a thumbnail that small next to a
                wrapped title reads as clutter rather than as an image.
                `max-sm:hidden` rather than `hidden sm:block` — the latter's
                `display: block` overrode the flex that centres the fallback
                phase mark, leaving the disc stranded in the top-left corner. */}
            <PostImage post={post} phaseSize={36} className="max-sm:hidden" />

            <div className="flex min-w-0 flex-col">
                {post.category && (
                    <Link
                        href={categoryShow(post.category.slug)}
                        // z-10 lifts it above the row-wide overlay link below,
                        // so the category stays separately clickable.
                        className="eyebrow relative z-10 w-fit text-brand-accent transition-colors hover:text-primary"
                    >
                        {post.category.name}
                    </Link>
                )}

                <h3 className="display-md mt-2">
                    <Link
                        href={postShow(post.slug)}
                        data-content-key={`post:${post.id}`}
                        data-content-source={contentSource}
                        data-content-location={contentLocation}
                        className="decoration-primary/50 underline-offset-[6px] transition-colors group-hover:underline after:absolute after:inset-0"
                        prefetch
                    >
                        {post.title}
                    </Link>
                </h3>

                {post.excerpt && (
                    <p className="mt-3 line-clamp-2 font-body leading-relaxed text-muted-foreground">
                        {post.excerpt}
                    </p>
                )}

                <div className="mt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-3">
                    <Dateline
                        date={post.published_at}
                        readingTime={post.reading_time_minutes}
                    />
                    <span className="micro-cta text-primary">
                        Read more
                        <ArrowRight className="size-3 transition-transform motion-safe:group-hover:translate-x-0.5" />
                    </span>
                </div>
            </div>
        </RevealItem>
    );
}
