import { Head } from '@inertiajs/react';

import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PageHeader, { postCount } from '@/components/public/page-header';
import PostList from '@/components/public/post-list';
import type { Paginated, PublicAuthor, PublicPostCard } from '@/types';

type Props = {
    author: PublicAuthor;
    posts: Paginated<PublicPostCard>;
};

export default function AuthorShow({ author, posts }: Props) {
    return (
        <>
            <Head title={author.name} />
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <PageHeader
                    eyebrow="Words by"
                    title={author.name}
                    description={author.bio}
                    meta={postCount(author.posts_count)}
                    avatarUrl={author.avatar_url}
                    avatarName={author.name}
                    socialLinks={author.social_links}
                />
                {posts.data.length > 0 ? (
                    <div className="grid gap-14">
                        <PostList
                            posts={posts.data}
                            contentSource="recommendation"
                            contentLocation="author_archive"
                        />
                        <Pagination
                            paginator={posts}
                            label={`Posts by ${author.name}`}
                        />
                    </div>
                ) : (
                    <EmptyState
                        title="No posts yet"
                        description={`${author.name} hasn't published anything yet.`}
                    />
                )}
            </div>
        </>
    );
}
