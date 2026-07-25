import { Head } from '@inertiajs/react';

import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PageHeader, { postCount } from '@/components/public/page-header';
import PostList from '@/components/public/post-list';
import type { Paginated, PublicCategory, PublicPostCard } from '@/types';

type Props = {
    category: PublicCategory;
    posts: Paginated<PublicPostCard>;
};

export default function CategoryShow({ category, posts }: Props) {
    return (
        <>
            <Head title={category.name} />
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <PageHeader
                    eyebrow="Destination"
                    title={category.name}
                    description={category.description}
                    meta={postCount(category.posts_count)}
                />
                {posts.data.length > 0 ? (
                    <div className="grid gap-14">
                        <PostList
                            posts={posts.data}
                            contentSource="category"
                            contentLocation="listing"
                        />
                        <Pagination
                            paginator={posts}
                            label={`${category.name} pages`}
                        />
                    </div>
                ) : (
                    <EmptyState
                        title="No posts here yet"
                        description={`Nothing has been published in ${category.name} so far.`}
                    />
                )}
            </div>
        </>
    );
}
