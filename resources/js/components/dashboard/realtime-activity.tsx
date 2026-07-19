import { useHttp } from '@inertiajs/react';
import { Activity, RefreshCw } from 'lucide-react';
import { useEffect, useEffectEvent } from 'react';
import AnalyticsRealtimeController from '@/actions/App/Http/Admin/Controllers/AnalyticsRealtimeController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type RealtimeResponse = {
    available: boolean;
    stale: boolean;
    readers: number | null;
    activePosts: Array<{
        contentKey: string;
        title: string | null;
        readers: number;
    }>;
    fetchedAt: string | null;
};

export default function RealtimeActivity() {
    const http = useHttp<Record<string, never>, RealtimeResponse>({});
    const refresh = useEffectEvent(() => {
        void http.get(AnalyticsRealtimeController.url());
    });

    useEffect(() => {
        refresh();
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        }, 60_000);

        return () => window.clearInterval(interval);
    }, []);

    const response = http.response;

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2">
                    <Activity className="size-5" aria-hidden />
                    Active now
                </CardTitle>
                <div className="flex items-center gap-2">
                    {response?.stale && <Badge variant="outline">Stale</Badge>}
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        aria-label="Refresh realtime activity"
                        disabled={http.processing}
                        onClick={() => {
                            void http.get(AnalyticsRealtimeController.url());
                        }}
                    >
                        <RefreshCw
                            className={http.processing ? 'animate-spin' : ''}
                            aria-hidden
                        />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {!response && http.processing ? (
                    <Skeleton className="h-20 w-full" />
                ) : response?.available ? (
                    <>
                        <p className="text-3xl font-semibold">
                            {response.readers ?? 0}
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                                measured readers in the last 30 minutes
                            </span>
                        </p>
                        {response.activePosts.length > 0 ? (
                            <ul className="grid gap-2 text-sm">
                                {response.activePosts.map((post) => (
                                    <li
                                        key={post.contentKey}
                                        className="flex items-center justify-between gap-3"
                                    >
                                        <span className="truncate">
                                            {post.title ?? 'Deleted post'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {post.readers}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No article activity is visible right now.
                            </p>
                        )}
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Realtime analytics is temporarily unavailable.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
