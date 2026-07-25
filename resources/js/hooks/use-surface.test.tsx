import { render } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { useSurface } from '@/hooks/use-surface';

function Surface({ surface }: { surface: 'admin' | 'public' }) {
    useSurface(surface);

    return null;
}

afterEach(() => document.documentElement.classList.remove('public-surface'));

describe('useSurface', () => {
    it('marks the document as the public surface', () => {
        render(<Surface surface="public" />);

        expect(document.documentElement).toHaveClass('public-surface');
    });

    it('leaves the admin surface on the default token set', () => {
        render(<Surface surface="admin" />);

        expect(document.documentElement).not.toHaveClass('public-surface');
    });

    // app.blade.php only stamps the class on the first paint, and Inertia never
    // re-renders that shell — so crossing between portals has to clear it.
    it('clears the class when the surface changes under it', () => {
        const { rerender } = render(<Surface surface="public" />);

        expect(document.documentElement).toHaveClass('public-surface');

        rerender(<Surface surface="admin" />);

        expect(document.documentElement).not.toHaveClass('public-surface');
    });

    it('restores the class when navigating back to the blog', () => {
        const { rerender } = render(<Surface surface="admin" />);

        rerender(<Surface surface="public" />);

        expect(document.documentElement).toHaveClass('public-surface');
    });
});
