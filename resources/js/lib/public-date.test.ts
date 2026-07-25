import { describe, expect, it } from 'vitest';
import {
    formatCompactDate,
    wasUpdatedAfterPublishing,
} from '@/lib/public-date';

describe('formatCompactDate', () => {
    it('formats a timestamp as day, short month, year', () => {
        expect(formatCompactDate('2026-03-14T09:30:00.000000Z')).toBe(
            '14 Mar 2026',
        );
    });

    it('drops the leading zero on the day', () => {
        expect(formatCompactDate('2026-06-04T00:00:00.000000Z')).toBe(
            '4 Jun 2026',
        );
    });

    it('returns an empty string for missing or unparseable values', () => {
        expect(formatCompactDate(null)).toBe('');
        expect(formatCompactDate('not a date')).toBe('');
        expect(formatCompactDate('2026-13-01T00:00:00Z')).toBe('');
    });

    /**
     * The whole reason this does not go through `Intl`: under SSR the server and
     * the browser would resolve a locale-less formatter differently, and a
     * timestamp late in the UTC day would render as two different days.
     */
    it('reads the UTC day, not the renderer local day', () => {
        expect(formatCompactDate('2026-03-14T23:50:00.000000Z')).toBe(
            '14 Mar 2026',
        );
        expect(formatCompactDate('2026-03-15T00:10:00.000000Z')).toBe(
            '15 Mar 2026',
        );
    });
});

describe('wasUpdatedAfterPublishing', () => {
    it('is false when the edit landed on the publication day', () => {
        expect(
            wasUpdatedAfterPublishing(
                '2026-03-14T09:30:00.000000Z',
                '2026-03-14T22:15:00.000000Z',
            ),
        ).toBe(false);
    });

    it('is true when the edit landed on a later day', () => {
        expect(
            wasUpdatedAfterPublishing(
                '2026-03-14T09:30:00.000000Z',
                '2026-03-16T08:00:00.000000Z',
            ),
        ).toBe(true);
    });

    // The server and the browser render in different time zones, so a
    // Date-based comparison would disagree either side of midnight UTC and
    // produce a hydration mismatch on the byline.
    it('compares UTC days, not the renderer local day', () => {
        expect(
            wasUpdatedAfterPublishing(
                '2026-03-14T23:50:00.000000Z',
                '2026-03-15T00:10:00.000000Z',
            ),
        ).toBe(true);

        expect(
            wasUpdatedAfterPublishing(
                '2026-03-14T00:10:00.000000Z',
                '2026-03-14T23:50:00.000000Z',
            ),
        ).toBe(false);
    });

    it('is false when either timestamp is missing', () => {
        expect(
            wasUpdatedAfterPublishing(null, '2026-03-16T08:00:00.000000Z'),
        ).toBe(false);
        expect(
            wasUpdatedAfterPublishing('2026-03-14T09:30:00.000000Z', null),
        ).toBe(false);
    });
});
