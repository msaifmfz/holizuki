import { Head } from '@inertiajs/react';

import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PageHeader, { postCount } from '@/components/public/page-header';
import PostList from '@/components/public/post-list';
import type { Paginated, PublicPostCard, PublicTag } from '@/types';

type Props = {
    tag: PublicTag;
    posts: Paginated<PublicPostCard>;
};

export default function TagShow({ tag, posts }: Props) {
    return (
        <>
            <Head title={`#${tag.name}`} />
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <PageHeader
                    eyebrow="Theme"
                    title={tag.name}
                    meta={postCount(tag.posts_count)}
                />
                {posts.data.length > 0 ? (
                    <div className="grid gap-14">
                        <PostList
                            posts={posts.data}
                            contentSource="category"
                            contentLocation="tag_listing"
                        />
                        <Pagination
                            paginator={posts}
                            label={`${tag.name} pages`}
                        />
                    </div>
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
