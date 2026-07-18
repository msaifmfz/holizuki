import { Head } from '@inertiajs/react';
import Pagination from '@/components/pagination';
import ArchiveHeader from '@/components/public/archive-header';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
import type { Paginated, PublicAuthor, PublicPostCard } from '@/types';

type Props = {
    author: PublicAuthor;
    posts: Paginated<PublicPostCard>;
};

export default function AuthorShow({ author, posts }: Props) {
    return (
        <>
            <Head title={author.name} />
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12">
                <ArchiveHeader
                    kicker="Author"
                    title={author.name}
                    description={author.bio}
                    postsCount={author.posts_count}
                    avatarUrl={author.avatar_url}
                    avatarName={author.name}
                    socialLinks={author.social_links}
                />
                {posts.data.length > 0 ? (
                    <>
                        <PostGrid posts={posts.data} />
                        <Pagination
                            paginator={posts}
                            label={`Posts by ${author.name}`}
                        />
                    </>
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
