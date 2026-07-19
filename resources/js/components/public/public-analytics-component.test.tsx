import { act, render, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { saveAnalyticsConsent } from '@/analytics/consent';
import { resetAnalyticsForTests } from '@/analytics/tracker';
import PublicAnalytics, {
    resetPublicAnalyticsForTests,
} from '@/components/public/public-analytics';

const initialPageUrl = '/posts/measured-post?private=ignored#section';

const page = {
    url: initialPageUrl,
    props: {
        analytics: {
            collectionEnabled: true,
            measurementId: 'G-TEST123',
            consentVersion: 'v1',
            consentDays: 180,
        },
        auth: { user: null },
        flash: { success: null, commentSubmitted: null },
        post: {
            id: 42,
            category: { slug: 'craft' },
            word_count_band: '1000_1499',
            published_at: '2026-07-01T00:00:00Z',
        },
    },
};

type RouterStartEvent = {
    detail: { visit: { only: string[]; except: string[] } };
};

const routerListeners: Record<string, (event: RouterStartEvent) => void> = {};

vi.mock('@inertiajs/react', () => ({
    router: {
        on: (name: string, callback: (event: RouterStartEvent) => void) => {
            routerListeners[name] = callback;

            return () => undefined;
        },
    },
    usePage: () => page,
}));

function startPartialNavigation() {
    routerListeners.start?.({
        detail: { visit: { only: ['comments'], except: [] } },
    });
}

function pageViewCount() {
    return (window.dataLayer ?? []).filter(
        (entry) =>
            Array.isArray(entry) &&
            entry[0] === 'event' &&
            entry[1] === 'page_view',
    ).length;
}

describe('public analytics component', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.head.innerHTML = '';
        window.dataLayer = undefined;
        window.gtag = undefined;
        page.url = initialPageUrl;
        resetAnalyticsForTests();
        resetPublicAnalyticsForTests();
    });

    it('starts the current page only after consent and sanitizes its location', async () => {
        render(<PublicAnalytics />);

        expect(document.querySelector('#holizuki-ga4')).toBeNull();

        act(() => {
            saveAnalyticsConsent('v1', 'accepted', 180);
        });

        await waitFor(() => {
            expect(document.querySelector('#holizuki-ga4')).not.toBeNull();
        });
        expect(window.dataLayer).toContainEqual([
            'event',
            'page_view',
            expect.objectContaining({
                page_location: `${window.location.origin}/posts/measured-post`,
                content_key: 'post:42',
            }),
        ]);
    });

    it('counts only visible active time before recording engagement', async () => {
        vi.useFakeTimers();
        saveAnalyticsConsent('v1', 'accepted', 180);
        const focus = vi.spyOn(document, 'hasFocus').mockReturnValue(true);
        const article = document.createElement('article');
        article.dataset.articleBody = '';
        article.getBoundingClientRect = () =>
            ({
                top: -500,
                bottom: 500,
                height: 1000,
                left: 0,
                right: 1000,
                width: 1000,
                x: 0,
                y: -500,
                toJSON: () => ({}),
            }) as DOMRect;
        document.body.append(article);

        render(<PublicAnalytics />);
        await act(async () => {
            await vi.advanceTimersByTimeAsync(29_000);
        });
        expect(window.dataLayer).not.toContainEqual([
            'event',
            'article_engaged',
            expect.anything(),
        ]);

        await act(async () => {
            await vi.advanceTimersByTimeAsync(1_000);
        });
        expect(window.dataLayer).toContainEqual([
            'event',
            'article_engaged',
            { content_key: 'post:42' },
        ]);

        article.remove();
        focus.mockRestore();
        vi.useRealTimers();
    });

    it('still tracks the page when consent arrives after a partial reload', async () => {
        const { rerender } = render(<PublicAnalytics />);

        act(() => {
            startPartialNavigation();
        });
        page.url = '/posts/measured-post?comments_page=2';
        rerender(<PublicAnalytics />);

        act(() => {
            saveAnalyticsConsent('v1', 'accepted', 180);
        });

        await waitFor(() => {
            expect(pageViewCount()).toBe(1);
        });
        expect(window.dataLayer).toContainEqual([
            'event',
            'page_view',
            expect.objectContaining({
                page_location: `${window.location.origin}/posts/measured-post`,
            }),
        ]);
    });

    it('does not count partial reload navigations as extra page views', async () => {
        saveAnalyticsConsent('v1', 'accepted', 180);
        const { rerender } = render(<PublicAnalytics />);

        await waitFor(() => {
            expect(pageViewCount()).toBe(1);
        });

        act(() => {
            startPartialNavigation();
        });
        page.url = '/posts/measured-post?comments_page=2';
        rerender(<PublicAnalytics />);

        expect(pageViewCount()).toBe(1);
    });
});
