import { Deferred, Head } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { audience } from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import AnalyticsChart from '@/components/dashboard/analytics-chart';
import type { AnalyticsChartPoint } from '@/components/dashboard/analytics-chart';
import MetricCard from '@/components/dashboard/metric-card';
import type { DashboardMetric } from '@/components/dashboard/metric-card';
import PeriodSelector from '@/components/dashboard/period-selector';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type DimensionRow = {
    value: string;
    readers: number;
    pageViews: number;
    previousReaders: number | null;
    share: number;
};

type Props = {
    period: { key: string; from: string; to: string; days: number };
    metrics: {
        readers: DashboardMetric;
        meaningfulReaders: DashboardMetric;
        readerActionRate: DashboardMetric;
        pageViews: DashboardMetric;
    };
    chart: { points: AnalyticsChartPoint[]; summary: string };
    channels: Array<{
        channel: string;
        readers: number;
        meaningfulReaders: number;
        actioningReaders: number;
    }>;
    countries?: DimensionRow[];
    devices?: DimensionRow[];
    sources?: DimensionRow[];
    landingPages?: DimensionRow[];
};

function DimensionTrend({ row }: { row: DimensionRow }) {
    if (row.previousReaders === null) {
        return null;
    }

    const Icon =
        row.readers > row.previousReaders
            ? ArrowUpRight
            : row.readers < row.previousReaders
              ? ArrowDownRight
              : Minus;
    const color =
        row.readers > row.previousReaders
            ? 'text-emerald-500'
            : row.readers < row.previousReaders
              ? 'text-rose-500'
              : 'text-muted-foreground';

    return (
        <Icon
            className={`size-3.5 ${color}`}
            aria-label={`Previously ${row.previousReaders} readers`}
        />
    );
}

function DimensionCard({
    title,
    dataKey,
    rows,
    emptyLabel,
}: {
    title: string;
    dataKey: string;
    rows: DimensionRow[] | undefined;
    emptyLabel: string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <Deferred
                    data={dataKey}
                    fallback={
                        <div className="grid gap-2">
                            <Skeleton className="h-6 w-full" />
                            <Skeleton className="h-6 w-4/5" />
                            <Skeleton className="h-6 w-3/5" />
                        </div>
                    }
                >
                    {rows && rows.length > 0 ? (
                        <ul className="grid gap-3">
                            {rows.map((row) => (
                                <li key={row.value} className="grid gap-1">
                                    <div className="flex items-center justify-between gap-2 text-sm">
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            <span className="truncate">
                                                {row.value}
                                            </span>
                                            <DimensionTrend row={row} />
                                        </span>
                                        <span className="shrink-0 text-muted-foreground">
                                            {new Intl.NumberFormat().format(
                                                row.readers,
                                            )}{' '}
                                            readers
                                        </span>
                                    </div>
                                    <div
                                        className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                        role="presentation"
                                    >
                                        <div
                                            className="h-full rounded-full bg-primary/70"
                                            style={{
                                                width: `${Math.max(row.share, 2)}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {emptyLabel}
                        </p>
                    )}
                </Deferred>
            </CardContent>
        </Card>
    );
}

export default function DashboardAudience({
    period,
    metrics,
    chart,
    channels,
    countries,
    devices,
    sources,
    landingPages,
}: Props) {
    return (
        <>
            <Head title="Audience" />
            <div className="mx-auto grid w-full max-w-7xl gap-6 p-4 lg:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Audience
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Exact active-user ranges and measured reader behavior.
                    </p>
                </header>
                <PeriodSelector period={period} routeUrl={audience.url()} />
                <div className="grid gap-4 sm:grid-cols-3">
                    <MetricCard title="Readers" metric={metrics.readers} />
                    <MetricCard
                        title="Meaningful readers"
                        metric={metrics.meaningfulReaders}
                    />
                    <MetricCard
                        title="Action rate"
                        metric={metrics.readerActionRate}
                    />
                </div>
                <AnalyticsChart points={chart.points} summary={chart.summary} />
                <div className="grid gap-4 md:grid-cols-2">
                    <DimensionCard
                        title="Countries"
                        dataKey="countries"
                        rows={countries}
                        emptyLabel="No country breakdown is ready for this period."
                    />
                    <DimensionCard
                        title="Devices"
                        dataKey="devices"
                        rows={devices}
                        emptyLabel="No device breakdown is ready for this period."
                    />
                    <DimensionCard
                        title="Traffic sources"
                        dataKey="sources"
                        rows={sources}
                        emptyLabel="No traffic-source breakdown is ready for this period."
                    />
                    <DimensionCard
                        title="Landing pages"
                        dataKey="landingPages"
                        rows={landingPages}
                        emptyLabel="No landing-page breakdown is ready for this period."
                    />
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Acquisition channels</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {channels.length > 0 ? (
                            <div className="divide-y">
                                {channels.map((channel) => (
                                    <div
                                        key={channel.channel}
                                        className="grid grid-cols-[1fr_auto_auto] gap-5 px-6 py-3 text-sm"
                                    >
                                        <span>{channel.channel}</span>
                                        <span>{channel.readers} readers</span>
                                        <span className="text-muted-foreground">
                                            {channel.meaningfulReaders}{' '}
                                            meaningful
                                        </span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="p-6 text-sm text-muted-foreground">
                                No exact channel snapshots are ready.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DashboardAudience.layout = {
    breadcrumbs: [{ title: 'Audience', href: audience() }],
};
