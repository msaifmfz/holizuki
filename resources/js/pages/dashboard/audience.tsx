import { Head } from '@inertiajs/react';
import { audience } from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import AnalyticsChart from '@/components/dashboard/analytics-chart';
import type { AnalyticsChartPoint } from '@/components/dashboard/analytics-chart';
import MetricCard from '@/components/dashboard/metric-card';
import type { DashboardMetric } from '@/components/dashboard/metric-card';
import PeriodSelector from '@/components/dashboard/period-selector';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
};

export default function DashboardAudience({
    period,
    metrics,
    chart,
    channels,
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
