import { Head } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import ArchiveHeader from '@/components/public/archive-header';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
import type { Paginated, PublicCategory, PublicPostCard } from '@/types';

type Props = {
    category: PublicCategory;
    posts: Paginated<PublicPostCard>;
};

export default function CategoryShow({ category, posts }: Props) {
    return (
        <>
            <Head title={category.name} />
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12">
                <ArchiveHeader
                    kicker="Category"
                    title={category.name}
                    description={category.description}
                    postsCount={category.posts_count}
                />
                {posts.data.length > 0 ? (
                    <>
                        <PostGrid
                            posts={posts.data}
                            contentSource="category"
                            contentLocation="listing"
                        />
                        <Pagination
                            paginator={posts}
                            label={`${category.name} pages`}
                        />
                    </>
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
