import { Head, Link } from '@inertiajs/react';

import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PageHeader, { postCount } from '@/components/public/page-header';
import PostList from '@/components/public/post-list';
import { cn } from '@/lib/utils';
import { archive } from '@/routes/public';
import type {
    Paginated,
    PublicArchivePeriod,
    PublicArchiveYear,
    PublicPostCard,
} from '@/types';

type Props = {
    period: PublicArchivePeriod;
    periods: PublicArchiveYear[];
    posts: Paginated<PublicPostCard>;
};

export default function Archive({ period, periods, posts }: Props) {
    return (
        <>
            <Head title={period.label} />
            <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-12">
                <PageHeader
                    eyebrow="Archive"
                    title={period.label}
                    description="Every published post, by the month it first appeared."
                    meta={postCount(posts.total)}
                />

                <MonthStrip periods={periods} period={period} />

                {posts.data.length > 0 ? (
                    <div className="grid gap-14">
                        <PostList
                            posts={posts.data}
                            contentSource="archive"
                            contentLocation="archive"
                        />
                        <Pagination paginator={posts} label="Archive pages" />
                    </div>
                ) : (
                    <EmptyState
                        title="Nothing from this stretch"
                        description="No posts were published in this period. Try another month, or browse everything."
                        action={
                            <Link
                                href={archive()}
                                className="micro-cta text-primary underline-offset-4 hover:underline"
                            >
                                All posts
                            </Link>
                        }
                    />
                )}
            </div>
        </>
    );
}

/**
 * The archive as a strip of months, each carrying a bar for how much was
 * published in it.
 *
 * Replaces a stack of `<details>` in a sidebar, which made you open a year to
 * find out whether it held anything. As a strip, a year is legible at a glance:
 * which months were busy and which were quiet.
 *
 * This originally showed the moon phase for the 15th of each month, to carry the
 * site's date device through. That was wrong and got removed: a calendar month
 * and a synodic month are within a day of each other in length, so the 15th of
 * successive months sits at almost the same lunar age — the discs came out
 * near-identical for months on end and took ~29 months to cycle. A phase belongs
 * to an instant, and only a post has one of those. So the bar reports the one
 * thing a month genuinely has, which is a count.
 */
function MonthStrip({
    periods,
    period,
}: {
    periods: PublicArchiveYear[];
    period: PublicArchivePeriod;
}) {
    if (periods.length === 0) {
        return null;
    }

    const showingAll = period.year === null;
    // Scale bars against the busiest month in the whole archive, so the strip is
    // comparable across years rather than only within a row.
    const busiest = Math.max(
        ...periods.flatMap((year) =>
            year.months.map((month) => month.posts_count),
        ),
        1,
    );

    return (
        <nav aria-label="Archive by month" className="grid gap-7">
            <div className="flex items-baseline justify-between border-t border-border pt-4">
                <p className="eyebrow text-brand-accent">By month</p>
                <Link
                    href={archive()}
                    aria-current={showingAll ? 'page' : undefined}
                    className={cn(
                        'eyebrow underline-offset-4 transition-colors hover:underline',
                        showingAll
                            ? 'text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    All posts
                </Link>
            </div>

            {periods.map((year) => (
                <div
                    key={year.year}
                    className="grid gap-3 sm:grid-cols-[4.5rem_1fr] sm:gap-5"
                >
                    <p className="meta-num pt-1 text-lg text-muted-foreground">
                        {year.year}
                    </p>

                    <ol className="flex flex-wrap gap-x-2 gap-y-2">
                        {year.months.map((month) => {
                            const active =
                                period.year === year.year &&
                                period.month === month.month;

                            return (
                                <li key={month.month}>
                                    <Link
                                        href={archive({
                                            year: year.year,
                                            month: month.month,
                                        })}
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        aria-label={`${month.label} — ${postCount(month.posts_count)}`}
                                        className={cn(
                                            'group grid w-[5.5rem] gap-1.5 border px-2.5 py-2 transition-colors',
                                            active
                                                ? 'border-primary bg-primary/10 text-foreground'
                                                : 'border-border text-muted-foreground hover:border-primary/60 hover:text-foreground',
                                        )}
                                    >
                                        <span className="flex items-baseline justify-between gap-2">
                                            <span className="eyebrow">
                                                {shortMonth(month.label)}
                                            </span>
                                            <span className="meta-num text-[0.6875rem] tabular-nums">
                                                {month.posts_count}
                                            </span>
                                        </span>
                                        <span
                                            aria-hidden
                                            className="block h-0.5 bg-border"
                                        >
                                            <span
                                                className={cn(
                                                    'block h-full',
                                                    active
                                                        ? 'bg-primary'
                                                        : 'bg-brand-accent',
                                                )}
                                                style={{
                                                    width: `${Math.max((month.posts_count / busiest) * 100, 6)}%`,
                                                }}
                                            />
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ol>
                </div>
            ))}
        </nav>
    );
}

/**
 * `January 2026` / `January` arrive from the server already localised for the
 * period; the strip only has room for the first three letters, and the year is
 * already the row label.
 */
function shortMonth(label: string): string {
    return label.slice(0, 3);
}
