import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import AnalyticsChart from '@/components/dashboard/analytics-chart';
import MetricCard from '@/components/dashboard/metric-card';
import type { DashboardMetric } from '@/components/dashboard/metric-card';

const metric = (value: number | null): DashboardMetric => ({
    value,
    previousValue: null,
    comparison: { state: 'unavailable', percent: null },
    tooltip: 'Exact consent-matched readers.',
    source: 'exact',
    measured: true,
    freshness: 'fresh',
});

describe('dashboard analytics components', () => {
    it('distinguishes a measured zero from unavailable data', () => {
        const { rerender } = render(
            <MetricCard title="Readers" metric={metric(0)} />,
        );

        expect(screen.getByText('0')).toHaveAttribute('data-state', 'ready');
        expect(screen.queryByText('Unavailable')).toBeNull();

        rerender(<MetricCard title="Readers" metric={metric(null)} />);

        expect(screen.getByText('Unavailable')).toHaveAttribute(
            'data-state',
            'unavailable',
        );
    });

    it('renders a sparkline only when a series with multiple points exists', () => {
        const withSpark: DashboardMetric = {
            ...metric(10),
            comparison: { state: 'increase', percent: 25 },
            spark: [2, 4, 10],
        };
        const { rerender } = render(
            <MetricCard title="Readers" metric={withSpark} />,
        );

        expect(screen.getByTestId('metric-sparkline')).toBeInTheDocument();
        expect(document.querySelector('svg polyline')).not.toBeNull();

        rerender(
            <MetricCard
                title="Readers"
                metric={{ ...metric(10), spark: [] }}
            />,
        );

        expect(screen.queryByTestId('metric-sparkline')).toBeNull();
    });

    it('renders an accessible dependency-free chart and textual summary', () => {
        render(
            <AnalyticsChart
                title="Weekly measured readers"
                points={[
                    {
                        date: '2026-07-06',
                        readers: 10,
                        meaningfulReaders: 4,
                    },
                    {
                        date: '2026-07-13',
                        readers: 15,
                        meaningfulReaders: 7,
                    },
                ]}
                summary="Two exact ISO-week points; the peak was 15 readers."
            />,
        );

        expect(
            screen.getByRole('img', { name: 'Weekly measured readers' }),
        ).toBeVisible();
        expect(
            screen.getByText(
                'Two exact ISO-week points; the peak was 15 readers.',
            ),
        ).toBeVisible();
        expect(document.querySelectorAll('svg path')).toHaveLength(2);
        expect(document.querySelectorAll('svg circle')).toHaveLength(4);
        expect(screen.getByText('Meaningful readers')).toBeVisible();
        expect(document.querySelector('canvas')).toBeNull();
    });
});
