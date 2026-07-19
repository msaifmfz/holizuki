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
    resetAnalyticsForTests,
    trackAnalyticsEvent,
} from '@/analytics/tracker';

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
        expect(window.dataLayer).toContainEqual([
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
        expect(window.dataLayer).toContainEqual([
            'event',
            'share',
            expect.objectContaining({ item_id: 'post:1' }),
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
        expect(window.dataLayer).toContainEqual([
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
