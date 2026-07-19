import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    ANALYTICS_CONSENT_KEY,
    clearGoogleAnalyticsCookies,
    readAnalyticsConsent,
    saveAnalyticsConsent,
} from '@/analytics/consent';

describe('analytics consent', () => {
    beforeEach(() => {
        window.localStorage.clear();
        document.cookie = '_ga=test; path=/';
        document.cookie = '_ga_TEST=test; path=/';
    });

    it('expires accept and decline decisions equally after the configured period', () => {
        const decidedAt = new Date('2026-01-01T00:00:00Z');

        for (const status of ['accepted', 'declined'] as const) {
            saveAnalyticsConsent('v1', status, 180, decidedAt);

            expect(
                readAnalyticsConsent('v1', new Date('2026-06-29T23:59:59Z'))
                    ?.status,
            ).toBe(status);
            expect(
                readAnalyticsConsent('v1', new Date('2026-06-30T00:00:00Z')),
            ).toBeNull();
        }
    });

    it('invalidates consent when the policy version changes', () => {
        saveAnalyticsConsent('v1', 'accepted', 180);

        expect(readAnalyticsConsent('v2')).toBeNull();
        expect(localStorage.getItem(ANALYTICS_CONSENT_KEY)).toBeNull();
    });

    it('clears known Google Analytics cookies on withdrawal', () => {
        const dispatch = vi.spyOn(window, 'dispatchEvent');
        clearGoogleAnalyticsCookies();

        expect(document.cookie).not.toContain('_ga=');
        expect(document.cookie).not.toContain('_ga_TEST=');
        dispatch.mockRestore();
    });
});
