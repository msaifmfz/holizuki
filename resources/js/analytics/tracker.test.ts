import { beforeEach, describe, expect, it } from 'vitest';
import { saveAnalyticsConsent } from '@/analytics/consent';
import {
    sanitizedPath,
    sanitizedUrl,
    validateAnalyticsEvent,
} from '@/analytics/events';
import {
    configureAnalytics,
    ensureGoogleAnalyticsLoaded,
    isAnalyticsEligible,
    pageViewParameters,
    resetAnalyticsForTests,
    trackAnalyticsEvent,
} from '@/analytics/tracker';

function queuedAnalyticsCommands() {
    return (window.dataLayer ?? []).map((entry) =>
        Array.from(entry as ArrayLike<unknown>),
    );
}

describe('analytics tracker', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.head.innerHTML = '';
        window.dataLayer = undefined;
        window.gtag = undefined;
        resetAnalyticsForTests();
        configureAnalytics({
            collectionEnabled: true,
            measurementId: 'G-TEST123',
            consentVersion: 'v1',
            eligible: true,
        });
    });

    it('never loads the tag before acceptance', () => {
        expect(ensureGoogleAnalyticsLoaded()).toBe(false);
        expect(document.querySelector('#holizuki-ga4')).toBeNull();

        saveAnalyticsConsent('v1', 'declined', 180);
        expect(ensureGoogleAnalyticsLoaded()).toBe(false);
        expect(document.querySelector('#holizuki-ga4')).toBeNull();
    });

    it('loads once after acceptance with explicit page views disabled', () => {
        saveAnalyticsConsent('v1', 'accepted', 180);

        expect(ensureGoogleAnalyticsLoaded()).toBe(true);
        expect(ensureGoogleAnalyticsLoaded()).toBe(true);
        expect(document.querySelectorAll('#holizuki-ga4')).toHaveLength(1);
        expect(Object.prototype.toString.call(window.dataLayer?.[0])).toBe(
            '[object Arguments]',
        );
        expect(queuedAnalyticsCommands()).toContainEqual([
            'config',
            'G-TEST123',
            expect.objectContaining({ send_page_view: false }),
        ]);
    });

    it('excludes author, authentication, settings, preview, and token pages', () => {
        expect(isAnalyticsEligible('/posts/a', 'administrator')).toBe(false);
        expect(isAnalyticsEligible('/dashboard', null)).toBe(false);
        expect(isAnalyticsEligible('/settings/profile', null)).toBe(false);
        expect(isAnalyticsEligible('/posts/1/preview', null)).toBe(false);
        expect(isAnalyticsEligible('/newsletter/confirm/secret', null)).toBe(
            false,
        );
        expect(isAnalyticsEligible('/account', 'reader')).toBe(false);
        expect(isAnalyticsEligible('/posts/public-post', 'reader')).toBe(true);
    });

    it('sanitizes URL data and rejects unknown or PII-like payload values', () => {
        expect(
            sanitizedUrl('https://example.com/posts/a?email=private#comments'),
        ).toBe('https://example.com/posts/a');
        expect(sanitizedPath('/posts/a?secret=yes#part')).toBe('/posts/a');
        expect(
            validateAnalyticsEvent('page_view', {
                page_location: 'https://example.com/posts/a',
                page_title: 'A useful article',
            }),
        ).toBe(true);
        expect(
            validateAnalyticsEvent('page_view', {
                page_location: 'https://example.com/?email=reader@example.com',
            }),
        ).toBe(false);
        expect(
            validateAnalyticsEvent('share', {
                method: 'copy',
                content_type: 'article',
                item_id: 'post:1',
                content_key: 'post:1',
                arbitrary: 'not allowed',
            } as never),
        ).toBe(false);
    });

    it('emits only an allowlisted event after consent', () => {
        saveAnalyticsConsent('v1', 'accepted', 180);

        expect(
            trackAnalyticsEvent('share', {
                method: 'copy',
                content_type: 'article',
                item_id: 'post:1',
                content_key: 'post:1',
            }),
        ).toBe(true);
        expect(queuedAnalyticsCommands()).toContainEqual([
            'event',
            'share',
            expect.objectContaining({ item_id: 'post:1' }),
        ]);
    });

    it('marks post page titles for realtime attribution and applies them to subsequent events', () => {
        saveAnalyticsConsent('v1', 'accepted', 180);
        const parameters = pageViewParameters(
            'https://example.com/posts/a',
            'A useful article - Holizuki',
            { id: 42 },
        );

        expect(parameters.page_title).toBe(
            '[post:42] A useful article - Holizuki',
        );
        const longPageTitle = pageViewParameters(
            'https://example.com/posts/a',
            'x'.repeat(300),
            { id: 42 },
        ).page_title;
        expect(longPageTitle).toHaveLength(256);
        expect(longPageTitle).toMatch(/^\[post:42\] /);
        expect(trackAnalyticsEvent('page_view', parameters)).toBe(true);
        expect(queuedAnalyticsCommands()).toContainEqual([
            'set',
            { page_title: '[post:42] A useful article - Holizuki' },
        ]);
    });

    it('omits absent optional attribution from a valid signup event', () => {
        saveAnalyticsConsent('v1', 'accepted', 180);

        expect(
            trackAnalyticsEvent('sign_up', {
                method: 'newsletter',
                content_key: undefined,
                source_content_key: undefined,
            }),
        ).toBe(true);
        expect(queuedAnalyticsCommands()).toContainEqual([
            'event',
            'sign_up',
            { method: 'newsletter' },
        ]);
    });

    it('accepts punctuation in titles but rejects unknown runtime event names', () => {
        expect(
            validateAnalyticsEvent('page_view', {
                page_location: 'https://example.com/posts/a',
                page_title: 'Why now? #1 reason',
            }),
        ).toBe(true);

        expect(
            validateAnalyticsEvent(
                'custom_event' as never,
                { content_key: 'post:1' } as never,
            ),
        ).toBe(false);
    });
});
