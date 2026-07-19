import PostCard from '@/components/public/post-card';
import type { PublicPostCard } from '@/types';

export default function PostGrid({
    posts,
    contentSource,
    contentLocation,
}: {
    posts: PublicPostCard[];
    contentSource?: string;
    contentLocation?: string;
}) {
    return (
        <div className="grid gap-x-6 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            {posts.map((post) => (
                <PostCard
                    key={post.id}
                    post={post}
                    contentSource={contentSource}
                    contentLocation={contentLocation}
                />
            ))}
        </div>
    );
}
