import { Head } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import ArchiveHeader from '@/components/public/archive-header';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
import type { Paginated, PublicPostCard, PublicTag } from '@/types';

type Props = {
    tag: PublicTag;
    posts: Paginated<PublicPostCard>;
};

export default function TagShow({ tag, posts }: Props) {
    return (
        <>
            <Head title={`#${tag.name}`} />
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12">
                <ArchiveHeader
                    kicker="Tag"
                    title={`#${tag.name}`}
                    postsCount={tag.posts_count}
                />
                {posts.data.length > 0 ? (
                    <>
                        <PostGrid posts={posts.data} />
                        <Pagination
                            paginator={posts}
                            label={`${tag.name} pages`}
                        />
                    </>
                ) : (
                    <EmptyState
                        title="No posts here yet"
                        description={`Nothing has been tagged with ${tag.name} so far.`}
                    />
                )}
            </div>
        </>
    );
}
