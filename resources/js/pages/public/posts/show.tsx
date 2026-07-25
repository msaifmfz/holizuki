import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import {
    motion,
    useReducedMotion,
    useScroll,
    useSpring,
    useTransform,
} from 'motion/react';
import { useRef } from 'react';

import AuthorByline from '@/components/public/author-byline';
import CommentSection from '@/components/public/comment-section';
import type {
    PrivateComment,
    PublicComment,
} from '@/components/public/comment-section';
import Dateline from '@/components/public/dateline';
import {
    Reveal,
    revealVariants,
    staggerVariants,
} from '@/components/public/motion';
import NewsletterForm from '@/components/public/newsletter-form';
import PostGrid from '@/components/public/post-grid';
import PostTableOfContents from '@/components/public/post-table-of-contents';
import PublicRichText, {
    ExpandableImage,
} from '@/components/public/public-rich-text';
import SectionBand from '@/components/public/section-band';
import ShareTools from '@/components/public/share-tools';
import AuthorSocialLinks from '@/components/public/social-links';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { usePostView } from '@/hooks/use-post-view';
import {
    formatCompactDate,
    wasUpdatedAfterPublishing,
} from '@/lib/public-date';
import { show as authorShow } from '@/routes/public/authors';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import { show as tagShow } from '@/routes/public/tags';
import type {
    PublicPostCard,
    PublicPostDetail,
    TableOfContentsItem,
} from '@/types';

/** Inertia's Link, made animatable — keeps SPA navigation and `prefetch`. */
const MotionLink = motion.create(Link);

type Props = {
    post: PublicPostDetail;
    related: PublicPostCard[];
    previous: PublicPostCard | null;
    next: PublicPostCard | null;
    table_of_contents: TableOfContentsItem[];
    comments: { data: PublicComment[]; [key: string]: unknown };
    myComments: PrivateComment[];
};

/**
 * A single article.
 *
 * The reading column is the whole point of the page, so it is set the way
 * tg-mari sets its own — roughly 68 characters at 19px with leading near 1.85 —
 * and the prose grid in public.css lets figures step out past that measure
 * without breaking the column. Headings inside the prose are the display face
 * against a serif body, which is the inversion the whole site is built on.
 */
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

    const articleRef = useRef<HTMLDivElement>(null);
    const heroRef = useRef<HTMLElement>(null);

    // Progress across the prose only, so the bar completes at the end of the
    // article rather than at the bottom of the comments.
    const { scrollYProgress } = useScroll({
        target: articleRef,
        offset: ['start start', 'end end'],
    });
    const progressScaleX = useSpring(scrollYProgress, {
        stiffness: 120,
        damping: 30,
        restDelta: 0.001,
    });

    // The cover drifts as the hero scrolls away, which reads as depth without
    // touching layout.
    //
    // Gated by hand: <MotionConfig reducedMotion="user"> only reaches values
    // that motion *animates*, and this is a scroll-bound motion value applied
    // straight through `style`. The progress bar is left alone — it is an
    // indicator rather than decoration.
    const prefersReducedMotion = useReducedMotion();
    const { scrollYProgress: heroProgress } = useScroll({
        target: heroRef,
        offset: ['start start', 'end start'],
    });
    const imageDrift = useTransform(heroProgress, [0, 1], [0, -26]);

    const showUpdated = wasUpdatedAfterPublishing(
        post.published_at,
        post.updated_at,
    );

    return (
        <>
            <Head title={post.seo_title ?? post.title} />

            <motion.div
                aria-hidden
                className="reading-progress"
                style={{ scaleX: progressScaleX }}
            />

            <header ref={heroRef}>
                <motion.div
                    className="mx-auto grid w-full max-w-6xl gap-7 px-4 pt-12 pb-10 sm:pt-16"
                    initial="hidden"
                    animate="visible"
                    variants={staggerVariants}
                >
                    <motion.div variants={revealVariants} className="max-w-4xl">
                        {post.category && (
                            <Link
                                href={categoryShow(post.category.slug)}
                                className="eyebrow text-brand-accent transition-colors hover:text-primary"
                            >
                                {post.category.name}
                            </Link>
                        )}
                        <h1 id="post-title" className="display-lg mt-4">
                            {post.title}
                        </h1>
                    </motion.div>

                    {post.excerpt && (
                        <motion.p
                            variants={revealVariants}
                            className="max-w-2xl font-body text-xl leading-relaxed text-muted-foreground"
                        >
                            {post.excerpt}
                        </motion.p>
                    )}

                    {/* The dateline block: the phase mark at reading size,
                        alongside the byline and the length of the piece. */}
                    <motion.div
                        variants={revealVariants}
                        className="flex flex-wrap items-center justify-between gap-x-8 gap-y-5 border-t border-border pt-6"
                    >
                        <div className="flex flex-wrap items-center gap-x-8 gap-y-5">
                            <AuthorByline author={post.author} size="lg" />
                            <Dateline
                                date={post.published_at}
                                readingTime={post.reading_time_minutes}
                                wordCountBand={post.word_count_band}
                                phaseSize={13}
                                labelPhase
                            />
                        </div>
                        {showUpdated && post.updated_at && (
                            <p className="eyebrow text-muted-foreground">
                                Updated {formatCompactDate(post.updated_at)}
                            </p>
                        )}
                    </motion.div>
                </motion.div>

                {post.featured_image_url && (
                    /*
                     * The cover runs wider than the reading column and wider than
                     * the page container — the article's own images step out to
                     * `wide` inside the prose grid, and the hero steps out
                     * further still, so the page opens at its widest and narrows
                     * into the text.
                     *
                     * Reuses the article's image component rather than a bespoke
                     * one, so the hero gets the same lightbox and the same
                     * caption treatment as any figure in the body.
                     */
                    <motion.div
                        className="mx-auto w-full max-w-[80rem] px-4 [&_.article-image]:mt-0"
                        style={{ y: prefersReducedMotion ? 0 : imageDrift }}
                    >
                        <ExpandableImage
                            attrs={{
                                src: post.featured_image_url,
                                alt: post.featured_image_alt ?? '',
                                caption: post.featured_image_caption,
                            }}
                        />
                    </motion.div>
                )}
            </header>

            <div className="mx-auto w-full max-w-6xl px-4 pt-14 pb-16">
                <div className="grid gap-12 lg:grid-cols-[minmax(0,1fr)_15rem] lg:items-start lg:gap-16">
                    <PostTableOfContents items={tableOfContents} />

                    <article
                        aria-labelledby="post-title"
                        className="min-w-0 lg:order-1"
                    >
                        <div ref={articleRef} data-article-body>
                            <PublicRichText
                                value={post.body}
                                tableOfContents={tableOfContents}
                                contentKey={`post:${post.id}`}
                            />
                        </div>

                        {/*
                         * End matter is capped at the figure width — the measure
                         * plus its breakout — rather than filling the column.
                         * Left free, its hairlines ran 500px past the right edge
                         * of every line of text above them, which read as
                         * unresolved rather than as a deliberate change of width.
                         */}
                        <div className="max-w-[47rem]">
                            {post.tags.length > 0 && (
                                <nav
                                    className="post-tags mt-14 flex flex-wrap gap-2 border-t border-border pt-6"
                                    aria-label="Post tags"
                                >
                                    {post.tags.map((tag) => (
                                        <Link
                                            key={tag.slug}
                                            href={tagShow(tag.slug)}
                                            className="eyebrow inline-flex border border-border px-3 py-2 text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                                        >
                                            {tag.name}
                                        </Link>
                                    ))}
                                </nav>
                            )}

                            <ShareTools
                                title={post.title}
                                methods={community.sharingMethods}
                                postId={post.id}
                            />

                            {post.author && (
                                <Reveal>
                                    <AuthorCard author={post.author} />
                                </Reveal>
                            )}

                            <Reveal className="mt-12 border-t border-border pt-10">
                                <NewsletterForm
                                    location="article_end"
                                    postId={post.id}
                                />
                            </Reveal>

                            {(previous || next) && (
                                <PostNavigation
                                    previous={previous}
                                    next={next}
                                />
                            )}

                            <CommentSection
                                postSlug={post.slug}
                                comments={comments}
                                myComments={myComments}
                            />
                        </div>
                    </article>
                </div>
            </div>

            {related.length > 0 && (
                <SectionBand className="related-posts">
                    <h2 className="section-head mb-10">
                        More from {post.category?.name ?? 'the blog'}
                    </h2>
                    <PostGrid
                        posts={related}
                        contentSource="related"
                        contentLocation="article_end"
                    />
                </SectionBand>
            )}
        </>
    );
}

/**
 * Previous and next, as a two-up split rather than a pair of cards. The hairline
 * between them does the work the card borders used to.
 */
function PostNavigation({
    previous,
    next,
}: {
    previous: PublicPostCard | null;
    next: PublicPostCard | null;
}) {
    return (
        <motion.nav
            className="post-navigation mt-14 grid border-t border-border sm:grid-cols-2"
            aria-label="Post navigation"
            data-reveal
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: '0px 0px -10% 0px' }}
            variants={staggerVariants}
        >
            {previous ? (
                <MotionLink
                    data-reveal
                    variants={revealVariants}
                    href={postShow(previous.slug)}
                    data-content-key={`post:${previous.id}`}
                    data-content-source="related"
                    data-content-location="post_navigation"
                    className="group grid content-start gap-3 border-b border-border py-7 pr-6 transition-colors hover:bg-card sm:border-r sm:pr-10"
                    prefetch
                >
                    <span className="micro-cta text-brand-accent">
                        <ArrowLeft
                            className="size-3 transition-transform duration-200 motion-safe:group-hover:-translate-x-0.5"
                            aria-hidden
                        />
                        Previous
                    </span>
                    <span className="font-display text-lg leading-snug font-medium text-balance [font-stretch:104%] decoration-primary/50 underline-offset-[6px] group-hover:underline">
                        {previous.title}
                    </span>
                </MotionLink>
            ) : (
                <span className="hidden sm:block" />
            )}
            {next && (
                <MotionLink
                    data-reveal
                    variants={revealVariants}
                    href={postShow(next.slug)}
                    data-content-key={`post:${next.id}`}
                    data-content-source="related"
                    data-content-location="post_navigation"
                    className="group grid content-start justify-items-end gap-3 border-b border-border py-7 text-right transition-colors hover:bg-card sm:pl-10"
                    prefetch
                >
                    <span className="micro-cta text-brand-accent">
                        Next
                        <ArrowRight
                            className="size-3 transition-transform duration-200 motion-safe:group-hover:translate-x-0.5"
                            aria-hidden
                        />
                    </span>
                    <span className="font-display text-lg leading-snug font-medium text-balance [font-stretch:104%] decoration-primary/50 underline-offset-[6px] group-hover:underline">
                        {next.title}
                    </span>
                </MotionLink>
            )}
        </motion.nav>
    );
}

function AuthorCard({
    author,
}: {
    author: NonNullable<PublicPostDetail['author']>;
}) {
    const getInitials = useInitials();

    return (
        <aside className="author-card mt-14 flex flex-wrap items-start gap-6 border-t border-border pt-8">
            <Avatar className="size-16 rounded-sm border border-border">
                <AvatarImage src={author.avatar_url ?? undefined} alt="" />
                <AvatarFallback className="rounded-sm bg-muted font-display text-lg text-muted-foreground">
                    {getInitials(author.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid min-w-0 flex-1 gap-2.5">
                <p className="eyebrow text-brand-accent">About the author</p>
                {author.slug ? (
                    <Link
                        href={authorShow(author.slug)}
                        className="display-md w-fit decoration-primary/40 underline-offset-4 hover:underline"
                    >
                        {author.name}
                    </Link>
                ) : (
                    <p className="display-md">{author.name}</p>
                )}
                {author.bio && (
                    <p className="font-body leading-relaxed text-muted-foreground">
                        {author.bio}
                    </p>
                )}
                <AuthorSocialLinks
                    links={author.social_links}
                    className="mt-1 -ml-2"
                />
            </div>
        </aside>
    );
}
