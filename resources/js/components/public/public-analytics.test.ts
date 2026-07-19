import { describe, expect, it } from 'vitest';
import {
    articleProgressPercent,
    claimPageView,
    resetPublicAnalyticsForTests,
} from '@/components/public/public-analytics';

describe('public analytics guards', () => {
    it('claims one page view for a route and ignores partial reloads', () => {
        resetPublicAnalyticsForTests();

        expect(claimPageView('/posts/one')).toBe(true);
        expect(claimPageView('/posts/one')).toBe(false);
        expect(claimPageView('/posts/two')).toBe(true);
        expect(claimPageView('/posts/one')).toBe(true);
        expect(claimPageView('/search?q=first')).toBe(true);
        expect(claimPageView('/search?q=second')).toBe(true);
    });

    it('measures progression against the article body only', () => {
        expect(articleProgressPercent(400, 1400, 1000, 500)).toBe(10);
        expect(articleProgressPercent(-500, 500, 1000, 500)).toBe(100);
        expect(articleProgressPercent(600, 1600, 1000, 500)).toBe(0);
        expect(articleProgressPercent(0, 0, 0, 500)).toBe(0);
    });
});
