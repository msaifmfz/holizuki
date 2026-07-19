import { Head, Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import Pagination from '@/components/pagination';
import EmptyState from '@/components/public/empty-state';
import PostGrid from '@/components/public/post-grid';
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
            <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12">
                <header className="grid gap-4 border-b pb-10">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Archive
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                        {period.label}
                    </h1>
                    <p className="max-w-2xl text-lg leading-8 text-muted-foreground">
                        Every published post, organized by when it first
                        appeared.
                    </p>
                </header>

                <div className="grid gap-10 lg:grid-cols-[14rem_minmax(0,1fr)]">
                    <aside className="self-start rounded-xl border p-4 lg:sticky lg:top-20">
                        <Link
                            href={archive()}
                            className="mb-3 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm font-semibold hover:bg-muted"
                            aria-current={
                                period.year === null ? 'page' : undefined
                            }
                        >
                            <CalendarDays className="size-4" aria-hidden />
                            All posts
                        </Link>
                        <nav aria-label="Archive periods">
                            <ol className="grid gap-2">
                                {periods.map((year) => (
                                    <li key={year.year}>
                                        <details
                                            open={period.year === year.year}
                                            className="group"
                                        >
                                            <summary className="cursor-pointer rounded-md px-2 py-1.5 text-sm font-medium hover:bg-muted">
                                                <span className="inline-flex w-[calc(100%-1rem)] items-center justify-between gap-2">
                                                    {year.year}
                                                    <span className="text-xs text-muted-foreground">
                                                        {year.posts_count}
                                                    </span>
                                                </span>
                                            </summary>
                                            <ol className="mt-1 grid gap-0.5 border-l pl-2">
                                                <li>
                                                    <Link
                                                        href={archive({
                                                            year: year.year,
                                                        })}
                                                        className="block rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        aria-current={
                                                            period.year ===
                                                                year.year &&
                                                            period.month ===
                                                                null
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                    >
                                                        All of {year.year}
                                                    </Link>
                                                </li>
                                                {year.months.map((month) => (
                                                    <li key={month.month}>
                                                        <Link
                                                            href={archive({
                                                                year: year.year,
                                                                month: month.month
                                                                    .toString()
                                                                    .padStart(
                                                                        2,
                                                                        '0',
                                                                    ),
                                                            })}
                                                            className="flex items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                                                            aria-current={
                                                                period.year ===
                                                                    year.year &&
                                                                period.month ===
                                                                    month.month
                                                                    ? 'page'
                                                                    : undefined
                                                            }
                                                        >
                                                            {month.label}
                                                            <span className="text-xs">
                                                                {
                                                                    month.posts_count
                                                                }
                                                            </span>
                                                        </Link>
                                                    </li>
                                                ))}
                                            </ol>
                                        </details>
                                    </li>
                                ))}
                            </ol>
                        </nav>
                    </aside>

                    <section aria-label={`Posts from ${period.label}`}>
                        {posts.data.length > 0 ? (
                            <div className="grid gap-10">
                                <PostGrid posts={posts.data} />
                                <Pagination
                                    paginator={posts}
                                    label="Archive pages"
                                />
                            </div>
                        ) : (
                            <EmptyState
                                title="No posts in this period"
                                description="Choose another month or year from the archive."
                            />
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
