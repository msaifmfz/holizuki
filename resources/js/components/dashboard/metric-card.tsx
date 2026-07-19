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
};

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
            </CardContent>
        </Card>
    );
}
