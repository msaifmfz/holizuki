import { describe, expect, it } from 'vitest';

import { lunarPhase } from './lunar-phase';

/**
 * Reference syzygies and quarters in UTC. The quarter fixtures are the ones
 * that carry weight: illumination is stationary at new and full, so a badly
 * wrong implementation still scores ~0 and ~1 there, whereas at the quarters it
 * changes fastest and a half-day error shows up immediately. An earlier draft
 * took the cycle position from mean elongation while taking illumination from
 * the corrected phase angle; only the quarter assertions caught it.
 */
const NEW_MOONS = [
    '2024-01-11T11:57:00Z',
    '2024-02-09T22:59:00Z',
    '2025-03-29T10:58:00Z',
];
const FULL_MOONS = [
    '2024-01-25T17:54:00Z',
    '2024-02-24T12:30:00Z',
    '2025-04-13T00:22:00Z',
];
const FIRST_QUARTERS = ['2024-01-18T03:53:00Z', '2025-04-05T02:15:00Z'];
const LAST_QUARTERS = ['2024-02-02T23:18:00Z'];

describe('lunarPhase', () => {
    it('returns null for absent or unparseable timestamps', () => {
        expect(lunarPhase(null)).toBeNull();
        expect(lunarPhase(undefined)).toBeNull();
        expect(lunarPhase('')).toBeNull();
        expect(lunarPhase('not a date')).toBeNull();
    });

    it.each(NEW_MOONS)('reads %s as a new moon', (iso) => {
        const phase = lunarPhase(iso)!;

        expect(phase.name).toBe('new moon');
        expect(phase.illumination).toBeLessThan(0.001);
        expect(phase.cycle).toBeCloseTo(0, 2);
    });

    it.each(FULL_MOONS)('reads %s as a full moon', (iso) => {
        const phase = lunarPhase(iso)!;

        expect(phase.name).toBe('full moon');
        expect(phase.illumination).toBeGreaterThan(0.999);
        expect(phase.cycle).toBeCloseTo(0.5, 2);
    });

    it.each(FIRST_QUARTERS)('reads %s as a waxing first quarter', (iso) => {
        const phase = lunarPhase(iso)!;

        expect(phase.name).toBe('first quarter');
        expect(phase.waxing).toBe(true);
        expect(phase.illumination).toBeCloseTo(0.5, 2);
        expect(phase.cycle).toBeCloseTo(0.25, 2);
    });

    it.each(LAST_QUARTERS)('reads %s as a waning last quarter', (iso) => {
        const phase = lunarPhase(iso)!;

        expect(phase.name).toBe('last quarter');
        expect(phase.waxing).toBe(false);
        expect(phase.illumination).toBeCloseTo(0.5, 2);
        expect(phase.cycle).toBeCloseTo(0.75, 2);
    });

    it('waxes between a new moon and the full moon that follows it', () => {
        expect(lunarPhase('2024-01-15T00:00:00Z')!.waxing).toBe(true);
        expect(lunarPhase('2024-01-22T00:00:00Z')!.waxing).toBe(true);
    });

    it('wanes between a full moon and the new moon that follows it', () => {
        expect(lunarPhase('2024-01-29T00:00:00Z')!.waxing).toBe(false);
        expect(lunarPhase('2024-02-06T00:00:00Z')!.waxing).toBe(false);
    });

    it('advances the cycle monotonically through a single lunation', () => {
        // Sampled from just after one new moon to just before the next, so the
        // series never wraps past 1.
        const cycles = Array.from(
            { length: 20 },
            (_, day) =>
                lunarPhase(new Date(Date.UTC(2024, 0, 12 + day)).toISOString())!
                    .cycle,
        );

        for (let index = 1; index < cycles.length; index += 1) {
            expect(cycles[index]).toBeGreaterThan(cycles[index - 1]);
        }
    });

    /**
     * The regression guard for the bug above: whatever the date, the name, the
     * cycle position, and the drawn illumination have to describe the same
     * moon. Sweeping two years means this holds for every phase rather than
     * only at the instants listed as fixtures.
     */
    it('keeps name, cycle and illumination consistent across two years', () => {
        for (let day = 0; day < 730; day += 1) {
            const iso = new Date(Date.UTC(2025, 0, 1 + day)).toISOString();
            const phase = lunarPhase(iso)!;

            expect(phase.cycle, iso).toBeGreaterThanOrEqual(0);
            expect(phase.cycle, iso).toBeLessThan(1);
            expect(phase.illumination, iso).toBeGreaterThanOrEqual(0);
            expect(phase.illumination, iso).toBeLessThanOrEqual(1);

            // Illumination is a cosine of the cycle position, so the two must
            // agree to within the truncation error of the series.
            const expected = (1 - Math.cos(phase.cycle * 2 * Math.PI)) / 2;
            expect(phase.illumination, iso).toBeCloseTo(expected, 2);

            if (phase.illumination > 0.999) {
                expect(phase.name, iso).toBe('full moon');
            }

            if (phase.illumination < 0.001) {
                expect(phase.name, iso).toBe('new moon');
            }

            if (Math.abs(phase.illumination - 0.5) < 0.005) {
                expect(phase.name, iso).toBe(
                    phase.waxing ? 'first quarter' : 'last quarter',
                );
            }
        }
    });

    it('is deterministic and time-zone independent, so SSR and the client agree', () => {
        // The same instant written three ways must produce the same mark.
        const utc = lunarPhase('2024-01-25T17:54:00Z')!;

        expect(lunarPhase('2024-01-26T02:54:00+09:00')).toEqual(utc);
        expect(lunarPhase('2024-01-25T12:54:00-05:00')).toEqual(utc);
    });
});
