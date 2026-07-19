import { Head, Link } from '@inertiajs/react';
import {
    post as postAnalytics,
    posts,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import AnalyticsChart from '@/components/dashboard/analytics-chart';
import type { AnalyticsChartPoint } from '@/components/dashboard/analytics-chart';
import PeriodSelector from '@/components/dashboard/period-selector';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    period: { key: string; from: string; to: string; days: number };
    post: { id: number; title: string; slug: string };
    metrics: {
        readers: number;
        meaningfulReaders: number;
        actioningReaders: number;
        pageViews: number;
        shares: number;
        signUps: number;
        comments: number;
    } | null;
    chart: { points: AnalyticsChartPoint[]; summary: string };
};

export default function DashboardPostShow({
    period,
    post,
    metrics,
    chart,
}: Props) {
    return (
        <>
            <Head title={`${post.title} analytics`} />
            <div className="mx-auto grid w-full max-w-6xl gap-6 p-4 lg:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Post analytics
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {post.title}
                        </h1>
                    </div>
                    <Button asChild variant="outline">
                        <Link href={posts()}>All posts</Link>
                    </Button>
                </header>
                <PeriodSelector
                    period={period}
                    routeUrl={postAnalytics.url(post.id)}
                />
                {metrics ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <PostMetric label="Readers" value={metrics.readers} />
                        <PostMetric
                            label="Meaningful readers"
                            value={metrics.meaningfulReaders}
                        />
                        <PostMetric label="Shares" value={metrics.shares} />
                        <PostMetric label="Sign-ups" value={metrics.signUps} />
                    </div>
                ) : (
                    <Card>
                        <CardContent className="py-8 text-sm text-muted-foreground">
                            This exact post snapshot is unavailable. Zero is
                            shown only after GA returns a successful zero-valued
                            range.
                        </CardContent>
                    </Card>
                )}
                <AnalyticsChart
                    points={chart.points}
                    summary={chart.summary}
                    title={`${post.title} measured readers`}
                />
            </div>
        </>
    );
}

function PostMetric({ label, value }: { label: string; value: number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent className="text-3xl font-semibold">
                {value.toLocaleString()}
            </CardContent>
        </Card>
    );
}
