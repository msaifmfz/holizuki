import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';

import Pagination from '@/components/pagination';
import Dateline from '@/components/public/dateline';
import EmptyState from '@/components/public/empty-state';
import MoonPhase from '@/components/public/moon-phase';
import { Reveal, RevealGroup, RevealItem } from '@/components/public/motion';
import PostImage from '@/components/public/post-image';
import PostList from '@/components/public/post-list';
import SectionBand from '@/components/public/section-band';
import { show as categoryShow } from '@/routes/public/categories';
import { show as postShow } from '@/routes/public/posts';
import type { Paginated, PublicPostCard } from '@/types';

/** `popular` carries a 30-day visitor count the other lists do not have. */
type PopularPost = PublicPostCard & { views_count: number };

type Props = {
    featured: PublicPostCard[];
    popular: PopularPost[];
    posts: Paginated<PublicPostCard>;
};

/**
 * The home page.
 *
 * Sections own their own containers rather than sitting inside one page-level
 * `max-w-6xl`, which is what lets the hero and the bands run edge to edge
 * without a negative-margin escape hack.
 */
export default function Home({ featured, popular, posts }: Props) {
    const showFeatured = featured.length > 0 && posts.current_page === 1;
    const [lead, ...supporting] = featured;

    return (
        <>
            <Head title="Home" />

            {showFeatured && <FeaturedHero post={lead} />}

            {showFeatured && supporting.length > 0 && (
                <section className="mx-auto w-full max-w-6xl px-4 py-14">
                    <RevealGroup className="grid border-t border-border md:grid-cols-2">
                        {supporting.slice(0, 2).map((post, index) => (
                            <SupportingPost
                                key={post.id}
                                post={post}
                                // Hairline between the pair, not around them.
                                className={
                                    index === 0
                                        ? 'md:border-r md:border-border md:pr-10'
                                        : 'md:pl-10'
                                }
                            />
                        ))}
                    </RevealGroup>
                </section>
            )}

            {showFeatured && popular.length > 0 && (
                <PopularBand posts={popular} />
            )}

            <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:py-20">
                <h2 className="section-head mb-10">
                    {showFeatured ? 'Fresh off the trip' : 'Latest dispatches'}
                </h2>

                {posts.data.length > 0 ? (
                    <div className="grid gap-16">
                        <LatestPosts posts={posts.data} />
                        <Pagination paginator={posts} label="Blog pages" />
                    </div>
                ) : (
                    !showFeatured && (
                        <EmptyState
                            title="No dispatches yet"
                            description="The first trip write-ups land here as soon as they're published. Check back soon."
                        />
                    )
                )}
            </section>
        </>
    );
}

/**
 * Full-bleed cover, then the title on the night band directly beneath it.
 *
 * The title used to sit *over* the photo behind two scrim gradients. That is
 * gone, and the reason is worth keeping: text over an arbitrary image cannot be
 * made reliably accessible here. The schema has no way to record whether a cover
 * is light or dark, so the scrim had to survive the worst upload; and because the
 * scrim was an absolutely-positioned sibling, axe could not resolve it at all and
 * measured the type against the page background instead — scoring the hero
 * 1.02:1 and failing the suite's `assertNoAccessibilityIssues` gate.
 *
 * Pixel sampling said the composited result was genuinely fine (5.03–6.77 over
 * pure white), so this was not a real legibility problem. But a design that only
 * a bespoke measurement can vouch for is a design nobody can maintain, and the
 * automated gate was right to refuse it. Moving the type onto a solid band makes
 * the contrast exact, constant, and checkable: 14.97:1 for the title, 7.48:1 for
 * the muted runs, 9.07:1 for the accent — the same numbers everywhere the band
 * is used. The image keeps its full-bleed scale; only the type moved.
 */
function FeaturedHero({ post }: { post: PublicPostCard }) {
    return (
        <section>
            {/* Through `PostImage` like every other cover on the site, so a
                missing file falls back to the phase mark instead of leaving the
                browser's broken-image icon and the alt text at full-bleed size.
                `priority` because this is the largest thing above the fold, and
                `border-0` because a full-bleed image has no edge to draw. */}
            <PostImage
                post={post}
                ratio="aspect-[4/3] sm:aspect-[2/1] lg:aspect-[21/9]"
                phaseSize={96}
                priority
                className="border-0"
            />

            <div className="band-night">
                <div className="mx-auto w-full max-w-6xl px-4 py-11 sm:py-14">
                    <div className="flex items-start justify-between gap-8">
                        <div className="max-w-4xl">
                            {post.category && (
                                <Link
                                    href={categoryShow(post.category.slug)}
                                    className="eyebrow text-night-accent transition-colors hover:text-night-foreground"
                                >
                                    {post.category.name}
                                </Link>
                            )}

                            <h1 className="display-xl mt-5 text-night-foreground">
                                <Link
                                    href={postShow(post.slug)}
                                    data-content-key={`post:${post.id}`}
                                    data-content-source="editorial"
                                    data-content-location="hero"
                                    prefetch
                                >
                                    {post.title}
                                </Link>
                            </h1>

                            {post.excerpt && (
                                <p className="mt-6 max-w-2xl font-body text-lg leading-relaxed text-night-muted sm:text-xl">
                                    {post.excerpt}
                                </p>
                            )}

                            <Dateline
                                date={post.published_at}
                                readingTime={post.reading_time_minutes}
                                className="mt-7 text-night-muted [&_time]:text-night-foreground"
                                phaseSize={13}
                            />
                        </div>

                        {/* The one large phase mark on the page, and the only
                            one announced to assistive tech. */}
                        <MoonPhase
                            date={post.published_at}
                            size={104}
                            label
                            className="hidden shrink-0 text-night-foreground lg:block"
                        />
                    </div>

                    {post.featured_image_caption && (
                        <p className="mt-9 max-w-md font-body text-sm text-night-muted italic">
                            {post.featured_image_caption}
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}

/** Featured posts two and three: type only, no card, hairline between. */
function SupportingPost({
    post,
    className,
}: {
    post: PublicPostCard;
    className?: string;
}) {
    return (
        <RevealItem className={`group relative py-8 ${className ?? ''}`}>
            {post.category && (
                <p className="eyebrow text-brand-accent">
                    {post.category.name}
                </p>
            )}

            <h3 className="display-md mt-3">
                <Link
                    href={postShow(post.slug)}
                    data-content-key={`post:${post.id}`}
                    data-content-source="editorial"
                    data-content-location="featured"
                    className="decoration-primary/50 underline-offset-[6px] transition-colors group-hover:underline after:absolute after:inset-0"
                    prefetch
                >
                    {post.title}
                </Link>
            </h3>

            {post.excerpt && (
                <p className="mt-3 line-clamp-2 font-body leading-relaxed text-muted-foreground">
                    {post.excerpt}
                </p>
            )}

            <Dateline
                date={post.published_at}
                readingTime={post.reading_time_minutes}
                className="mt-4"
            />
        </RevealItem>
    );
}

/**
 * Most-read, on the night band.
 *
 * The only numbered list on the site, because it is the only one where the order
 * means something: `views_count` is a real 30-day ranking. Numbering the latest
 * posts or the categories would be decoration wearing the clothes of data.
 */
function PopularBand({ posts }: { posts: PopularPost[] }) {
    return (
        <SectionBand tone="night">
            <div className="flex flex-wrap items-baseline justify-between gap-4">
                <h2 className="section-head">Most read this month</h2>
                <p className="eyebrow text-night-muted">Last 30 days</p>
            </div>

            <RevealGroup className="mt-10 grid border-t border-night-muted/25 md:grid-cols-2 md:gap-x-14">
                {posts.map((post, index) => (
                    <RevealItem
                        key={post.id}
                        className="group relative flex items-baseline gap-5 border-b border-night-muted/25 py-5"
                    >
                        <span
                            aria-hidden
                            className="meta-num w-8 shrink-0 text-lg text-night-accent/70"
                        >
                            {String(index + 1).padStart(2, '0')}
                        </span>

                        <div className="min-w-0 flex-1">
                            <h3 className="font-display text-lg leading-snug [font-stretch:104%]">
                                <Link
                                    href={postShow(post.slug)}
                                    data-content-key={`post:${post.id}`}
                                    data-content-source="recommendation"
                                    data-content-location="popular"
                                    className="decoration-night-accent/60 underline-offset-[6px] group-hover:underline after:absolute after:inset-0"
                                    prefetch
                                >
                                    {post.title}
                                </Link>
                            </h3>
                            <Dateline
                                date={post.published_at}
                                readingTime={post.reading_time_minutes}
                                className="mt-2 text-night-muted"
                            />
                        </div>

                        <span className="meta-num shrink-0 text-xs text-night-muted">
                            {formatViews(post.views_count)}
                        </span>
                    </RevealItem>
                ))}
            </RevealGroup>
        </SectionBand>
    );
}

/**
 * How many compact rows sit beside the lead. Chosen so the two columns come out
 * roughly level: the lead is a 4:3 image plus four blocks of type, which is
 * about five rows tall. Any more and the right column runs on past the lead,
 * leaving a column of dead space — which is what a straight `[lead, ...rest]`
 * split produced with a full page of twelve.
 */
const ROWS_BESIDE_LEAD = 5;

/**
 * The asymmetric latest block, taken from Budget Traveller: the newest post gets
 * a large image and its excerpt, the next few stack beside it as compact rows,
 * and anything left over continues below in the same row shape the listing pages
 * use. It replaces a grid of twelve identical cards, where the newest post was
 * indistinguishable from the twelfth.
 */
function LatestPosts({ posts }: { posts: PublicPostCard[] }) {
    const [lead, ...rest] = posts;
    const beside = rest.slice(0, ROWS_BESIDE_LEAD);
    const remainder = rest.slice(ROWS_BESIDE_LEAD);

    return (
        <div className="grid gap-14">
            <LeadAndRows lead={lead} rows={beside} />

            {remainder.length > 0 && (
                <PostList
                    posts={remainder}
                    contentSource="recommendation"
                    contentLocation="recent"
                />
            )}
        </div>
    );
}

function LeadAndRows({
    lead,
    rows,
}: {
    lead: PublicPostCard;
    rows: PublicPostCard[];
}) {
    return (
        <div className="grid gap-12 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
            <Reveal as="article" className="group relative flex flex-col">
                <PostImage post={lead} ratio="aspect-[4/3]" phaseSize={64} />

                {lead.category && (
                    <p className="eyebrow mt-5 text-brand-accent">
                        {lead.category.name}
                    </p>
                )}

                <h3 className="display-lg mt-3 text-[clamp(1.75rem,3vw,2.5rem)]">
                    <Link
                        href={postShow(lead.slug)}
                        data-content-key={`post:${lead.id}`}
                        data-content-source="recommendation"
                        data-content-location="recent"
                        className="decoration-primary/50 underline-offset-[8px] transition-colors group-hover:underline after:absolute after:inset-0"
                        prefetch
                    >
                        {lead.title}
                    </Link>
                </h3>

                {lead.excerpt && (
                    <p className="mt-4 font-body text-lg leading-relaxed text-muted-foreground">
                        {lead.excerpt}
                    </p>
                )}

                <div className="mt-5 flex flex-wrap items-center justify-between gap-4">
                    <Dateline
                        date={lead.published_at}
                        readingTime={lead.reading_time_minutes}
                    />
                    <span className="micro-cta text-primary">
                        Read more
                        <ArrowRight className="size-3 transition-transform motion-safe:group-hover:translate-x-0.5" />
                    </span>
                </div>
            </Reveal>

            {rows.length > 0 && (
                <RevealGroup className="grid content-start border-t border-border">
                    {rows.map((post) => (
                        <CompactRow key={post.id} post={post} />
                    ))}
                </RevealGroup>
            )}
        </div>
    );
}

function CompactRow({ post }: { post: PublicPostCard }) {
    return (
        <RevealItem className="group relative grid grid-cols-[5.5rem_1fr] items-start gap-4 border-b border-border py-5 sm:grid-cols-[7rem_1fr] sm:gap-5">
            <PostImage post={post} phaseSize={26} />

            <div className="min-w-0">
                <h3 className="font-display text-base leading-snug font-medium [font-stretch:102%] sm:text-lg">
                    <Link
                        href={postShow(post.slug)}
                        data-content-key={`post:${post.id}`}
                        data-content-source="recommendation"
                        data-content-location="recent"
                        className="decoration-primary/50 underline-offset-[5px] transition-colors group-hover:underline after:absolute after:inset-0"
                        prefetch
                    >
                        {post.title}
                    </Link>
                </h3>
                <Dateline
                    date={post.published_at}
                    readingTime={post.reading_time_minutes}
                    className="mt-2"
                />
            </div>
        </RevealItem>
    );
}

/** `2.4k` past a thousand — a precise count is noise next to a rank. */
function formatViews(count: number): string {
    return count >= 1000 ? `${(count / 1000).toFixed(1)}k` : String(count);
}
