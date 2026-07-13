import { describe, expect, it } from 'vitest';
import {
    localDateTimeToUtc,
    slugify,
    toLocalDateTimeInput,
} from '@/lib/post-editor';

describe('post editor helpers', () => {
    it('creates stable URL-safe slugs', () => {
        expect(slugify('  A Café & Moonlight Story! ')).toBe(
            'a-cafe-moonlight-story',
        );
    });

    it('round trips a browser-local schedule through UTC', () => {
        const localValue = toLocalDateTimeInput('2026-07-12T14:30:00.000Z');

        expect(localDateTimeToUtc(localValue)).toBe(
            new Date(localValue).toISOString(),
        );
    });
});
