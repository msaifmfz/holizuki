import { Deferred, Form, Head, Link } from '@inertiajs/react';
import { Award, Gauge, Lightbulb, Users } from 'lucide-react';
import {
    achievements,
    audience,
    index,
    post as postAnalytics,
    posts,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import { update as updateInsight } from '@/actions/App/Http/Admin/Controllers/AnalyticsInsightController';
import AnalyticsChart from '@/components/dashboard/analytics-chart';
import type { AnalyticsChartPoint } from '@/components/dashboard/analytics-chart';
import MetricCard from '@/components/dashboard/metric-card';
import type { DashboardMetric } from '@/components/dashboard/metric-card';
import PeriodSelector from '@/components/dashboard/period-selector';
import RealtimeActivity from '@/components/dashboard/realtime-activity';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Props = {
    enabled: boolean;
    period: { key: string; from: string; to: string; days: number };
    freshness: { state: string; refreshedAt: string | null };
    narrative: string | null;
    metrics: {
        readers: DashboardMetric;
        meaningfulReaders: DashboardMetric;
        readerActionRate: DashboardMetric;
        pageViews: DashboardMetric;
    };
    chart: {
        resolution: string;
        points: AnalyticsChartPoint[];
        summary: string;
    };
    snapshotFallback: boolean;
    snapshotWindow: { from: string; to: string } | null;
    newMilestones: Array<{ code: string; achievedAt: string }>;
    momentum?: {
        score: number | null;
        level: string | null;
        components: Record<string, unknown>;
        freshness: string;
    };
    communityTotals: {
        approvedComments: number;
        activeSubscribers: number;
        label: string;
    };
    recommendations?: Array<{
        id: number;
        ruleId: string;
        confidence: string;
        observation: string;
        suggestedAction: string;
        postId: number | null;
    }>;
    topPosts?: Array<{
        post: { id: number; title: string };
        readers: number;
        meaningfulReaders: number;
    }>;
};

export default function Dashboard({
    enabled,
    period,
    freshness,
    narrative,
    metrics,
    chart,
    snapshotFallback,
    snapshotWindow,
    newMilestones,
    momentum,
    communityTotals,
    recommendations,
    topPosts,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 lg:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Author momentum
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Consent-matched audience signals and exact community
                            progress. Freshness: {freshness.state}
                            {freshness.refreshedAt
                                ? ` · ${new Date(freshness.refreshedAt).toLocaleString()}`
                                : ''}
                        </p>
                        {enabled && narrative && (
                            <p className="mt-1 text-sm font-medium">
                                {narrative}
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        {enabled && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={audience()}>Audience</Link>
                            </Button>
                        )}
                        <Button asChild variant="outline" size="sm">
                            <Link href={achievements()}>Achievements</Link>
                        </Button>
                    </div>
                </header>

                {enabled && (
                    <PeriodSelector period={period} routeUrl={index.url()} />
                )}

                {enabled && snapshotFallback && snapshotWindow && (
                    <p className="text-sm text-muted-foreground">
                        Today&apos;s window has not synced yet — showing the
                        most recent completed snapshot ({snapshotWindow.from} –{' '}
                        {snapshotWindow.to}).
                    </p>
                )}

                {newMilestones.length > 0 && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                        <CardContent className="flex flex-wrap items-center gap-3 py-4 text-sm">
                            <Award
                                className="size-5 text-amber-500"
                                aria-hidden
                            />
                            <span className="font-medium">
                                New since your last visit:
                            </span>
                            {newMilestones.map((milestone) => (
                                <Badge
                                    key={`${milestone.code}-${milestone.achievedAt}`}
                                    variant="secondary"
                                    className="capitalize"
                                >
                                    {milestone.code.replaceAll('_', ' ')}
                                </Badge>
                            ))}
                            <Link
                                href={achievements()}
                                className="ml-auto font-medium underline underline-offset-4"
                            >
                                View achievements
                            </Link>
                        </CardContent>
                    </Card>
                )}

                {!enabled && (
                    <Card className="border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                        <CardContent className="py-5 text-sm">
                            Analytics dashboard rollout is disabled. Community
                            totals remain available.
                        </CardContent>
                    </Card>
                )}

                {enabled && (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <MetricCard
                                title="Readers"
                                metric={metrics.readers}
                            />
                            <MetricCard
                                title="Meaningful readers"
                                metric={metrics.meaningfulReaders}
                            />
                            <MetricCard
                                title="Reader action rate"
                                metric={metrics.readerActionRate}
                            />
                            <MetricCard
                                title="Page views"
                                metric={metrics.pageViews}
                            />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                            <AnalyticsChart
                                points={chart.points}
                                summary={`${chart.summary} Daily and weekly readers are chart points only; headline Readers remains exact.`}
                            />
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Gauge className="size-5" aria-hidden />
                                        Momentum
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-3">
                                    <Deferred
                                        data="momentum"
                                        fallback={
                                            <Skeleton className="h-24 w-full" />
                                        }
                                    >
                                        {momentum?.score == null ? (
                                            <p className="text-sm text-muted-foreground">
                                                Momentum builds from your
                                                publishing rhythm and reader
                                                trends. It appears once enough
                                                measured history exists.
                                            </p>
                                        ) : (
                                            <>
                                                <p className="text-5xl font-semibold">
                                                    {momentum.score}
                                                    <span className="text-base text-muted-foreground">
                                                        /100
                                                    </span>
                                                </p>
                                                <p className="text-muted-foreground capitalize">
                                                    {momentum.level}
                                                </p>
                                            </>
                                        )}
                                    </Deferred>
                                </CardContent>
                            </Card>
                        </div>

                        <RealtimeActivity />
                    </>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-5" aria-hidden />
                                Community
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-2xl font-semibold">
                                    {communityTotals.approvedComments}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Approved comments
                                </p>
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">
                                    {communityTotals.activeSubscribers}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Active subscribers
                                </p>
                            </div>
                            <p className="col-span-2 text-xs text-muted-foreground">
                                {communityTotals.label}
                            </p>
                        </CardContent>
                    </Card>

                    {enabled && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Lightbulb className="size-5" aria-hidden />
                                    Next best actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Deferred
                                    data="recommendations"
                                    fallback={
                                        <Skeleton className="h-24 w-full" />
                                    }
                                >
                                    {recommendations &&
                                    recommendations.length > 0 ? (
                                        <ul className="grid gap-3">
                                            {recommendations.map(
                                                (recommendation) => (
                                                    <li
                                                        key={recommendation.id}
                                                        className="grid gap-2 rounded-lg border p-3"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Badge
                                                                variant="outline"
                                                                className="capitalize"
                                                            >
                                                                {
                                                                    recommendation.confidence
                                                                }
                                                            </Badge>
                                                            {recommendation.postId ? (
                                                                <Link
                                                                    href={postAnalytics(
                                                                        recommendation.postId,
                                                                    )}
                                                                    className="font-medium hover:underline"
                                                                >
                                                                    {
                                                                        recommendation.observation
                                                                    }
                                                                </Link>
                                                            ) : (
                                                                <p className="font-medium">
                                                                    {
                                                                        recommendation.observation
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                recommendation.suggestedAction
                                                            }
                                                        </p>
                                                        <Form
                                                            {...updateInsight.form(
                                                                recommendation.id,
                                                            )}
                                                            className="flex flex-wrap items-center gap-2"
                                                        >
                                                            <label
                                                                htmlFor={
                                                                    'insight-action-' +
                                                                    recommendation.id
                                                                }
                                                                className="sr-only"
                                                            >
                                                                Recommendation
                                                                action
                                                            </label>
                                                            <select
                                                                id={
                                                                    'insight-action-' +
                                                                    recommendation.id
                                                                }
                                                                name="reason"
                                                                defaultValue="snooze"
                                                                className="h-8 rounded-md border bg-background px-2 text-xs"
                                                            >
                                                                <option value="snooze">
                                                                    Snooze 7
                                                                    days
                                                                </option>
                                                                <option value="already_completed">
                                                                    Already
                                                                    completed
                                                                </option>
                                                                <option value="not_relevant">
                                                                    Not relevant
                                                                </option>
                                                                <option value="intentionally_designed">
                                                                    Intentionally
                                                                    designed
                                                                </option>
                                                                <option value="insufficient_context">
                                                                    Insufficient
                                                                    context
                                                                </option>
                                                                <option value="data_incorrect">
                                                                    Data appears
                                                                    incorrect
                                                                </option>
                                                            </select>
                                                            <Button
                                                                type="submit"
                                                                size="sm"
                                                                variant="outline"
                                                            >
                                                                Apply
                                                            </Button>
                                                        </Form>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-muted-foreground">
                                            Recommendations appear only when
                                            measured evidence clears the
                                            confidence threshold.
                                        </p>
                                    )}
                                </Deferred>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {enabled && (
                    <Card>
                        <CardHeader className="flex-row items-center justify-between">
                            <CardTitle>Top posts</CardTitle>
                            <Button asChild size="sm" variant="outline">
                                <Link href={posts()}>View all</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="topPosts"
                                fallback={<Skeleton className="h-32 w-full" />}
                            >
                                {topPosts && topPosts.length > 0 ? (
                                    <ol className="divide-y">
                                        {topPosts.map((entry) => (
                                            <li
                                                key={entry.post.id}
                                                className="flex items-center justify-between gap-4 py-3"
                                            >
                                                <Link
                                                    href={postAnalytics(
                                                        entry.post.id,
                                                        {
                                                            query: {
                                                                period: period.key,
                                                                from:
                                                                    period.key ===
                                                                    'custom'
                                                                        ? period.from
                                                                        : undefined,
                                                                to:
                                                                    period.key ===
                                                                    'custom'
                                                                        ? period.to
                                                                        : undefined,
                                                            },
                                                        },
                                                    )}
                                                    className="font-medium hover:underline"
                                                >
                                                    {entry.post.title}
                                                </Link>
                                                <span className="text-sm text-muted-foreground">
                                                    {entry.readers} readers
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No exact post snapshots are ready for
                                        this period.
                                    </p>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: index() }],
};
