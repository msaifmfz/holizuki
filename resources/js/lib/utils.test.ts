import { describe, expect, it } from 'vitest';
import { cn, toUrl } from '@/lib/utils';

describe('frontend utilities', () => {
    it('merges conflicting Tailwind classes', () => {
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });

    it('normalizes Inertia href values', () => {
        expect(toUrl('/settings/profile')).toBe('/settings/profile');
        expect(toUrl({ url: '/dashboard', method: 'get' })).toBe('/dashboard');
    });
});
