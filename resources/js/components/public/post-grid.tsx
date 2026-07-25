import { RevealGroup } from '@/components/public/motion';
import PostCard from '@/components/public/post-card';
import { cn } from '@/lib/utils';
import type { PublicPostCard } from '@/types';

/**
 * A row of vertical cards.
 *
 * This used to be the listing shape for the entire site — seven call sites, all
 * getting the same three-column grid. It is now only the closer at the end of an
 * article: everything above it there is one narrow column of prose, so a
 * horizontal three-up is the page's last change of rhythm. Listing pages use
 * `PostList` rows.
 */
export default function PostGrid({
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
            className={cn(
                'grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3',
                className,
            )}
        >
            {posts.map((post) => (
                <PostCard
                    key={post.id}
                    post={post}
                    contentSource={contentSource}
                    contentLocation={contentLocation}
                />
            ))}
        </RevealGroup>
    );
}
