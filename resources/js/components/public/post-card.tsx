import { Link } from '@inertiajs/react';

import Dateline from '@/components/public/dateline';
import { RevealItem } from '@/components/public/motion';
import PostImage from '@/components/public/post-image';
import { cn } from '@/lib/utils';
import { show as postShow } from '@/routes/public/posts';
import type { PublicPostCard } from '@/types';

/**
 * The vertical post card — image over title, used where a change of rhythm is
 * wanted: the pair under the home hero and the three-up at the end of an
 * article. Listing pages use `PostList` rows instead.
 *
 * No card chrome. The old version was a `rounded-3xl border` box that lifted 6px
 * on hover; both read as an app surface rather than as a page, and with the
 * radius now at 2px a bordered box would just look like a bordered box. What is
 * left is the image, a hairline, and type.
 */
export default function PostCard({
    post,
    contentSource,
    contentLocation,
    className,
}: {
    post: PublicPostCard;
    contentSource?: string;
    contentLocation?: string;
    className?: string;
}) {
    return (
        <RevealItem className={cn('group relative flex flex-col', className)}>
            <PostImage post={post} phaseSize={44} />

            {post.category && (
                <p className="eyebrow mt-4 text-brand-accent">
                    {post.category.name}
                </p>
            )}

            <h3 className="display-md mt-2.5">
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

            <Dateline
                date={post.published_at}
                readingTime={post.reading_time_minutes}
                className="mt-4"
            />
        </RevealItem>
    );
}
