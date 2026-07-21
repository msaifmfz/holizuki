import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type DashboardMetric = {
    value: number | null;
    previousValue: number | null;
    comparison: {
        state: 'increase' | 'decrease' | 'steady' | 'unavailable';
        percent: number | null;
    };
    tooltip: string;
    source: 'exact';
    measured: true;
    unit?: 'percent';
    freshness: string;
    spark?: number[];
};

function Sparkline({
    values,
    state,
}: {
    values: number[];
    state: DashboardMetric['comparison']['state'];
}) {
    if (values.length < 2) {
        return null;
    }

    const minimum = Math.min(...values);
    const range = Math.max(...values) - minimum || 1;
    const points = values
        .map(
            (value, index) =>
                `${((index / (values.length - 1)) * 100).toFixed(2)},${(
                    26 -
                    ((value - minimum) / range) * 24
                ).toFixed(2)}`,
        )
        .join(' ');
    const color =
        state === 'increase'
            ? 'text-emerald-500'
            : state === 'decrease'
              ? 'text-rose-500'
              : 'text-muted-foreground';

    return (
        <svg
            viewBox="0 0 100 28"
            preserveAspectRatio="none"
            className={`h-7 w-full ${color}`}
            data-testid="metric-sparkline"
            aria-hidden
        >
            <polyline
                points={points}
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

export default function MetricCard({
    title,
    metric,
}: {
    title: string;
    metric: DashboardMetric;
}) {
    const value =
        metric.value === null
            ? 'Unavailable'
            : metric.unit === 'percent'
              ? `${metric.value}%`
              : new Intl.NumberFormat().format(metric.value);
    const comparison = metric.comparison;
    const Icon =
        comparison.state === 'increase'
            ? ArrowUpRight
            : comparison.state === 'decrease'
              ? ArrowDownRight
              : Minus;

    return (
        <Card>
            <CardHeader className="gap-1 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <p
                    className="text-xs text-muted-foreground"
                    title={metric.tooltip}
                >
                    Measured · exact range
                </p>
            </CardHeader>
            <CardContent className="grid gap-2">
                <p
                    className="text-3xl font-semibold tracking-tight"
                    data-state={metric.value === null ? 'unavailable' : 'ready'}
                >
                    {value}
                </p>
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                    <Icon className="size-3.5" aria-hidden />
                    {comparison.percent === null
                        ? 'No complete comparison'
                        : `${Math.abs(comparison.percent)}% ${comparison.state === 'increase' ? 'up' : comparison.state === 'decrease' ? 'down' : 'change'} from prior period`}
                </p>
                {metric.spark && (
                    <Sparkline values={metric.spark} state={comparison.state} />
                )}
            </CardContent>
        </Card>
    );
}
