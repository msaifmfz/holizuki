import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Github,
    Globe,
    Linkedin,
    Twitter,
} from 'lucide-react';
import AuthorByline from '@/components/public/author-byline';
import CommentSection from '@/components/public/comment-section';
import type {
    PrivateComment,
    PublicComment,
} from '@/components/public/comment-section';
import NewsletterForm from '@/components/public/newsletter-form';
import PostGrid from '@/components/public/post-grid';
import PostTableOfContents from '@/components/public/post-table-of-contents';
import PublicRichText, {
    ExpandableImage,
} from '@/components/public/public-rich-text';
import ShareTools from '@/components/public/share-tools';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { usePostView } from '@/hooks/use-post-view';
import { show as authorShow } from '@/routes/public/authors';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import { show as tagShow } from '@/routes/public/tags';
import type {
    PublicPostCard,
    PublicPostDetail,
    SocialLinks,
    TableOfContentsItem,
} from '@/types';

const socialIcons: Array<{
    key: keyof SocialLinks;
    label: string;
    icon: typeof Globe;
}> = [
    { key: 'website', label: 'Website', icon: Globe },
    { key: 'x', label: 'X (Twitter)', icon: Twitter },
    { key: 'github', label: 'GitHub', icon: Github },
    { key: 'linkedin', label: 'LinkedIn', icon: Linkedin },
];

type Props = {
    post: PublicPostDetail;
    related: PublicPostCard[];
    previous: PublicPostCard | null;
    next: PublicPostCard | null;
    table_of_contents: TableOfContentsItem[];
    comments: { data: PublicComment[]; [key: string]: unknown };
    myComments: PrivateComment[];
};

export default function PublicPostShow({
    post,
    related,
    previous,
    next,
    table_of_contents: tableOfContents,
    comments,
    myComments,
}: Props) {
    const { community } = usePage().props;
    usePostView(post.slug);

    return (
        <>
            <Head title={post.seo_title ?? post.title} />

            <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:py-16">
                <header className="mx-auto mb-10 grid max-w-3xl gap-5 text-center">
                    <p className="flex items-center justify-center gap-3 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        {post.category && (
                            <Link
                                href={categoryShow(post.category.slug)}
                                className="transition-colors hover:text-foreground"
                            >
                                {post.category.name}
                            </Link>
                        )}
                    </p>
                    <h1
                        id="post-title"
                        className="font-display text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl"
                    >
                        {post.title}
                    </h1>
                    {post.excerpt && (
                        <p className="mx-auto max-w-2xl text-lg leading-8 text-muted-foreground">
                            {post.excerpt}
                        </p>
                    )}
                    <AuthorByline
                        author={post.author}
                        date={post.published_at}
                        size="md"
                        className="justify-center"
                        readingTime={post.reading_time_minutes}
                    />
                </header>

                {post.featured_image_url && (
                    <div className="mx-auto mb-10 max-w-4xl">
                        <ExpandableImage
                            attrs={{
                                src: post.featured_image_url,
                                alt: post.featured_image_alt,
                                caption: post.featured_image_caption,
                            }}
                        />
                    </div>
                )}

                <div className="grid gap-8 lg:grid-cols-[minmax(0,48rem)_15rem] lg:items-start lg:justify-center">
                    <PostTableOfContents items={tableOfContents} />
                    <article
                        aria-labelledby="post-title"
                        className="min-w-0 lg:order-1"
                    >
                        <div data-article-body>
                            <PublicRichText
                                value={post.body}
                                tableOfContents={tableOfContents}
                                contentKey={`post:${post.id}`}
                            />
                        </div>

                        <ShareTools
                            title={post.title}
                            methods={community.sharingMethods}
                            postId={post.id}
                        />

                        <NewsletterForm
                            location="article_end"
                            postId={post.id}
                            className="mt-8"
                        />

                        {post.tags.length > 0 && (
                            <nav
                                className="post-tags mt-10 flex flex-wrap gap-2"
                                aria-label="Post tags"
                            >
                                {post.tags.map((tag) => (
                                    <Badge
                                        key={tag.slug}
                                        asChild
                                        variant="secondary"
                                    >
                                        <Link href={tagShow(tag.slug)}>
                                            #{tag.name}
                                        </Link>
                                    </Badge>
                                ))}
                            </nav>
                        )}

                        {(previous || next) && (
                            <PostNavigation previous={previous} next={next} />
                        )}

                        {post.author && <AuthorCard author={post.author} />}

                        <CommentSection
                            postSlug={post.slug}
                            comments={comments}
                            myComments={myComments}
                        />
                    </article>
                </div>
            </div>

            {related.length > 0 && (
                <section className="related-posts mx-auto w-full max-w-6xl border-t px-4 py-12">
                    <h2 className="mb-8 flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        More in {post.category?.name ?? 'the blog'}
                    </h2>
                    <PostGrid
                        posts={related}
                        contentSource="related"
                        contentLocation="article_end"
                    />
                </section>
            )}
        </>
    );
}

function PostNavigation({
    previous,
    next,
}: {
    previous: PublicPostCard | null;
    next: PublicPostCard | null;
}) {
    return (
        <nav
            className="post-navigation mt-12 grid gap-3 border-y py-6 sm:grid-cols-2"
            aria-label="Post navigation"
        >
            {previous ? (
                <Link
                    href={postShow(previous.slug)}
                    data-content-key={`post:${previous.id}`}
                    data-content-source="related"
                    data-content-location="post_navigation"
                    className="group grid gap-1 rounded-lg p-3 transition-colors hover:bg-muted"
                    prefetch
                >
                    <span className="flex items-center gap-1 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <ArrowLeft className="size-3.5" aria-hidden />
                        Previous post
                    </span>
                    <span className="font-display font-semibold group-hover:underline group-hover:underline-offset-4">
                        {previous.title}
                    </span>
                </Link>
            ) : (
                <span />
            )}
            {next && (
                <Link
                    href={postShow(next.slug)}
                    data-content-key={`post:${next.id}`}
                    data-content-source="related"
                    data-content-location="post_navigation"
                    className="group grid gap-1 rounded-lg p-3 text-right transition-colors hover:bg-muted"
                    prefetch
                >
                    <span className="flex items-center justify-end gap-1 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        Next post
                        <ArrowRight className="size-3.5" aria-hidden />
                    </span>
                    <span className="font-display font-semibold group-hover:underline group-hover:underline-offset-4">
                        {next.title}
                    </span>
                </Link>
            )}
        </nav>
    );
}

function AuthorCard({
    author,
}: {
    author: NonNullable<PublicPostDetail['author']>;
}) {
    const getInitials = useInitials();

    return (
        <aside className="author-card mt-12 flex flex-wrap items-start gap-4 rounded-xl border bg-muted/30 p-6">
            <Avatar className="size-14 border">
                <AvatarImage src={author.avatar_url ?? undefined} alt="" />
                <AvatarFallback>{getInitials(author.name)}</AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 flex-1 gap-1.5">
                <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
                    Written by
                </p>
                {author.slug ? (
                    <Link
                        href={authorShow(author.slug)}
                        className="font-display text-lg font-semibold hover:underline"
                    >
                        {author.name}
                    </Link>
                ) : (
                    <p className="font-display text-lg font-semibold">
                        {author.name}
                    </p>
                )}
                {author.bio && (
                    <p className="text-sm leading-6 text-muted-foreground">
                        {author.bio}
                    </p>
                )}
                {author.social_links && (
                    <div className="author-social-links mt-1 flex items-center gap-1">
                        {socialIcons.map(
                            ({ key, label, icon: Icon }) =>
                                author.social_links?.[key] && (
                                    <a
                                        key={key}
                                        href={author.social_links[key]}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={label}
                                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        <Icon className="size-4" />
                                    </a>
                                ),
                        )}
                    </div>
                )}
            </div>
        </aside>
    );
}
