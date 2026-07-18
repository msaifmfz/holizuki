import { Head, Link } from '@inertiajs/react';
import { Github, Globe, Linkedin, Twitter } from 'lucide-react';
import AuthorByline from '@/components/public/author-byline';
import PostGrid from '@/components/public/post-grid';
import RichTextEditor from '@/components/rich-text-editor';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { show as authorShow } from '@/routes/public/authors';
import { show as categoryShow } from '@/routes/public/categories';
import { show as tagShow } from '@/routes/public/tags';
import type { PublicPostCard, PublicPostDetail, SocialLinks } from '@/types';

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
};

export default function PublicPostShow({ post, related }: Props) {
    return (
        <>
            <Head title={post.title} />

            <article className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-5 text-center">
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
                    <h1 className="font-display text-4xl leading-tight font-semibold tracking-tight text-balance sm:text-5xl">
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
                    />
                </header>

                {post.featured_image_url && (
                    <img
                        src={post.featured_image_url}
                        alt={post.featured_image_alt ?? ''}
                        className="mb-10 aspect-video w-full rounded-xl border object-cover shadow-sm"
                    />
                )}

                <RichTextEditor
                    value={post.body}
                    readOnly
                    className="border-0"
                />

                {post.tags.length > 0 && (
                    <nav
                        className="mt-10 flex flex-wrap gap-2"
                        aria-label="Post tags"
                    >
                        {post.tags.map((tag) => (
                            <Badge key={tag.slug} asChild variant="secondary">
                                <Link href={tagShow(tag.slug)}>
                                    #{tag.name}
                                </Link>
                            </Badge>
                        ))}
                    </nav>
                )}

                {post.author && <AuthorCard author={post.author} />}
            </article>

            {related.length > 0 && (
                <section className="mx-auto w-full max-w-6xl border-t px-4 py-12">
                    <h2 className="mb-8 flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        More in {post.category?.name ?? 'the blog'}
                    </h2>
                    <PostGrid posts={related} />
                </section>
            )}
        </>
    );
}

function AuthorCard({
    author,
}: {
    author: NonNullable<PublicPostDetail['author']>;
}) {
    const getInitials = useInitials();

    return (
        <aside className="mt-12 flex flex-wrap items-start gap-4 rounded-xl border bg-muted/30 p-6">
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
                    <div className="mt-1 flex items-center gap-1">
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
