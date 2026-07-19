import { useHttp } from '@inertiajs/react';
import { useEffect, useEffectEvent } from 'react';
import { store as recordPostView } from '@/routes/public/posts/views';

const REQUIRED_VISIBLE_TIME = 10_000;

export function usePostView(slug: string): void {
    const request = useHttp<Record<string, never>, void>({});
    const utcDate = new Date().toISOString().slice(0, 10);
    const storageKey = `post-view:${slug}:${utcDate}`;
    const sendView = useEffectEvent(async () => {
        if (window.sessionStorage.getItem(storageKey) === 'sent') {
            return;
        }

        window.sessionStorage.setItem(storageKey, 'sent');

        try {
            await request.post(recordPostView.url(slug));
        } catch {
            window.sessionStorage.removeItem(storageKey);
        }
    });

    useEffect(() => {
        if (window.sessionStorage.getItem(storageKey) === 'sent') {
            return;
        }

        let visibleSince = document.hidden ? null : performance.now();
        let elapsedVisibleTime = 0;
        let timeout: number | null = null;

        const clearTimer = () => {
            if (timeout !== null) {
                window.clearTimeout(timeout);
                timeout = null;
            }
        };

        const schedule = () => {
            clearTimer();

            if (document.hidden) {
                return;
            }

            visibleSince = performance.now();
            timeout = window.setTimeout(
                () => {
                    elapsedVisibleTime = REQUIRED_VISIBLE_TIME;
                    void sendView();
                },
                Math.max(0, REQUIRED_VISIBLE_TIME - elapsedVisibleTime),
            );
        };

        const handleVisibilityChange = () => {
            if (document.hidden) {
                if (visibleSince !== null) {
                    elapsedVisibleTime += performance.now() - visibleSince;
                    visibleSince = null;
                }

                clearTimer();

                return;
            }

            schedule();
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);
        schedule();

        return () => {
            clearTimer();
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, [slug, storageKey]);
}
