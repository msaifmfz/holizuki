export type AnalyticsChartPoint = {
    date: string;
    readers: number;
    meaningfulReaders: number;
};

export default function AnalyticsChart({
    points,
    summary,
    title = 'Measured readers over time',
}: {
    points: AnalyticsChartPoint[];
    summary: string;
    title?: string;
}) {
    const width = 800;
    const height = 260;
    const padding = 24;
    const max = Math.max(1, ...points.map((point) => point.readers));
    const coordinatesFor = (value: (point: AnalyticsChartPoint) => number) =>
        points.map((point, index) => ({
            x:
                points.length === 1
                    ? width / 2
                    : padding +
                      (index / Math.max(points.length - 1, 1)) *
                          (width - padding * 2),
            y: height - padding - (value(point) / max) * (height - padding * 2),
        }));
    const pathFor = (coordinates: Array<{ x: number; y: number }>) =>
        coordinates
            .map(
                (point, index) =>
                    `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`,
            )
            .join(' ');
    const readerCoordinates = coordinatesFor((point) => point.readers);
    const meaningfulCoordinates = coordinatesFor(
        (point) => point.meaningfulReaders,
    );
    const titleId = `analytics-chart-${title.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-')}`;

    return (
        <figure className="grid gap-3 rounded-xl border bg-card p-4 sm:p-6">
            <figcaption id={titleId} className="font-semibold">
                {title}
            </figcaption>
            {points.length === 0 ? (
                <div className="grid min-h-56 place-items-center rounded-lg bg-muted/30 text-sm text-muted-foreground">
                    Gathering measured chart data.
                </div>
            ) : (
                <>
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        role="img"
                        aria-labelledby={titleId}
                        className="h-auto w-full overflow-visible text-primary"
                    >
                        <path
                            d={pathFor(meaningfulCoordinates)}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeDasharray="6 6"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            vectorEffect="non-scaling-stroke"
                            className="text-muted-foreground"
                        />
                        <path
                            d={pathFor(readerCoordinates)}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="4"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            vectorEffect="non-scaling-stroke"
                        />
                        {meaningfulCoordinates.map((coordinate, index) => (
                            <circle
                                key={`meaningful-${points[index].date}-${index}`}
                                cx={coordinate.x}
                                cy={coordinate.y}
                                r="3.5"
                                fill="currentColor"
                                className="text-muted-foreground"
                            >
                                <title>
                                    {points[index].date}:{' '}
                                    {points[index].meaningfulReaders} meaningful
                                    readers
                                </title>
                            </circle>
                        ))}
                        {readerCoordinates.map((coordinate, index) => (
                            <circle
                                key={`${points[index].date}-${index}`}
                                cx={coordinate.x}
                                cy={coordinate.y}
                                r="5"
                                fill="currentColor"
                            >
                                <title>
                                    {points[index].date}:{' '}
                                    {points[index].readers} readers
                                </title>
                            </circle>
                        ))}
                    </svg>
                    <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span
                                className="h-1 w-4 rounded-full bg-primary"
                                aria-hidden
                            />
                            Readers
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span
                                className="w-4 border-t-2 border-dashed border-muted-foreground"
                                aria-hidden
                            />
                            Meaningful readers
                        </span>
                    </div>
                </>
            )}
            <p className="text-sm text-muted-foreground">{summary}</p>
        </figure>
    );
}
