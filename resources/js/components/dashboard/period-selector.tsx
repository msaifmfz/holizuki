import { router, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    requestSnapshot,
    snapshotStatus,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Period = { key: string; from: string; to: string; days: number };
type SnapshotStatus = {
    id: number;
    status: 'queued' | 'preparing' | 'ready' | 'failed';
    error: string | null;
};

const MAX_POLL_ATTEMPTS = 30;

export default function PeriodSelector({
    period,
    routeUrl,
}: {
    period: Period;
    routeUrl: string;
}) {
    const [customOpen, setCustomOpen] = useState(period.key === 'custom');
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [pollTimedOut, setPollTimedOut] = useState(false);
    const pollTimer = useRef<number | null>(null);
    const pollAttempts = useRef(0);
    const http = useHttp<
        { period: 'custom'; from: string; to: string },
        SnapshotStatus
    >({ period: 'custom', from, to });

    useEffect(
        () => () => {
            if (pollTimer.current !== null) {
                window.clearTimeout(pollTimer.current);
            }
        },
        [],
    );

    const select = (key: string) => {
        if (key === 'custom') {
            setCustomOpen(true);

            return;
        }

        router.get(routeUrl, { period: key }, { preserveScroll: true });
    };

    const poll = (id: number) => {
        if (pollAttempts.current >= MAX_POLL_ATTEMPTS) {
            setPollTimedOut(true);

            return;
        }

        pollAttempts.current += 1;
        pollTimer.current = window.setTimeout(() => {
            void http.get(snapshotStatus.url(id), {
                onSuccess: (response) => {
                    if (response.status === 'ready') {
                        router.get(routeUrl, {
                            period: 'custom',
                            from,
                            to,
                        });
                    } else if (response.status !== 'failed') {
                        poll(id);
                    }
                },
            });
        }, 2000);
    };

    const submitCustom = (event: React.FormEvent) => {
        event.preventDefault();
        pollAttempts.current = 0;
        setPollTimedOut(false);
        void http.post(requestSnapshot.url(), {
            onSuccess: (response) => {
                if (response.status === 'ready') {
                    router.get(routeUrl, { period: 'custom', from, to });
                } else {
                    poll(response.id);
                }
            },
        });
    };

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap gap-2" aria-label="Analytics period">
                {[
                    ['7d', '7 days'],
                    ['28d', '28 days'],
                    ['90d', '90 days'],
                    ['year', 'Year to date'],
                    ['custom', 'Custom'],
                ].map(([key, label]) => (
                    <Button
                        key={key}
                        type="button"
                        size="sm"
                        variant={period.key === key ? 'default' : 'outline'}
                        onClick={() => select(key)}
                    >
                        {label}
                    </Button>
                ))}
            </div>
            {customOpen && (
                <form
                    onSubmit={submitCustom}
                    className="flex flex-wrap items-end gap-3 rounded-lg border bg-muted/20 p-3"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor="analytics-from">From</Label>
                        <Input
                            id="analytics-from"
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                                http.setData('from', event.target.value);
                            }}
                            required
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="analytics-to">To</Label>
                        <Input
                            id="analytics-to"
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                                http.setData('to', event.target.value);
                            }}
                            required
                        />
                    </div>
                    <Button type="submit" disabled={http.processing}>
                        {http.processing ? 'Preparing…' : 'Apply custom range'}
                    </Button>
                    {http.response?.status === 'failed' && (
                        <p className="text-sm text-destructive">
                            {http.response.error ??
                                'The range could not be prepared.'}
                        </p>
                    )}
                    {pollTimedOut && (
                        <p className="text-sm text-destructive">
                            The range is still preparing. Apply the custom range
                            again in a minute to retry.
                        </p>
                    )}
                </form>
            )}
        </div>
    );
}
