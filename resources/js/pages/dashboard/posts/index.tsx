import { Deferred, Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import {
    post as postAnalytics,
    posts,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import PeriodSelector from '@/components/dashboard/period-selector';
import Pagination from '@/components/pagination';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { Paginated } from '@/types';

type PostRow = {
    post: { id: number; title: string; slug: string | null };
    readers: number;
    meaningfulReaders: number;
    actioningReaders: number;
    shares: number;
    signUps: number;
};

type Props = {
    period: { key: string; from: string; to: string; days: number };
    posts?: Paginated<PostRow>;
};

export default function DashboardPosts({ period, posts: rows }: Props) {
    const query = {
        period: period.key,
        from: period.key === 'custom' ? period.from : undefined,
        to: period.key === 'custom' ? period.to : undefined,
    };

    return (
        <>
            <Head title="Post analytics" />
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 lg:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Post analytics
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Exact consent-matched metrics for each article. The
                        author filter is hidden while one Administrator owns
                        publishing.
                    </p>
                </header>
                <PeriodSelector period={period} routeUrl={posts.url()} />
                <Card>
                    <CardContent className="p-0">
                        <Deferred
                            data="posts"
                            fallback={
                                <div className="grid gap-3 p-5">
                                    <Skeleton className="h-14" />
                                    <Skeleton className="h-14" />
                                    <Skeleton className="h-14" />
                                </div>
                            }
                        >
                            {rows && rows.data.length > 0 ? (
                                <div className="divide-y">
                                    {rows.data.map((entry) => (
                                        <Link
                                            key={entry.post.id}
                                            href={postAnalytics(entry.post.id, {
                                                query,
                                            })}
                                            className="grid gap-3 p-4 transition-colors hover:bg-muted/40 sm:grid-cols-[1fr_repeat(4,7rem)_auto] sm:items-center"
                                        >
                                            <span className="font-medium">
                                                {entry.post.title}
                                            </span>
                                            <Stat
                                                label="Readers"
                                                value={entry.readers}
                                            />
                                            <Stat
                                                label="Meaningful"
                                                value={entry.meaningfulReaders}
                                            />
                                            <Stat
                                                label="Shares"
                                                value={entry.shares}
                                            />
                                            <Stat
                                                label="Sign-ups"
                                                value={entry.signUps}
                                            />
                                            <ArrowRight
                                                className="size-4 text-muted-foreground"
                                                aria-hidden
                                            />
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <p className="p-8 text-center text-sm text-muted-foreground">
                                    No exact post snapshots are available for
                                    this period.
                                </p>
                            )}
                        </Deferred>
                    </CardContent>
                </Card>
                {rows && (
                    <Pagination paginator={rows} label="Post analytics pages" />
                )}
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <span>
            <span className="block text-xs text-muted-foreground">{label}</span>
            <span className="font-medium">{value.toLocaleString()}</span>
        </span>
    );
}

DashboardPosts.layout = {
    breadcrumbs: [{ title: 'Post analytics', href: posts() }],
};
