import { useEffect, useLayoutEffect } from 'react';

/**
 * A layout effect in the browser, a no-op effect during SSR — React warns if
 * `useLayoutEffect` is used while server rendering.
 */
const useIsomorphicLayoutEffect =
    typeof document === 'undefined' ? useEffect : useLayoutEffect;

/**
 * Keeps the `public-surface` class on <html> in step with the layout that is
 * actually rendering.
 *
 * app.blade.php stamps the class for the first paint, but Inertia never
 * re-renders the Blade shell — without this, following a link from the blog
 * into the admin console (or the logo on a login page back out to the blog)
 * leaves the previous surface's token set in place. The layout that renders is
 * the ground truth here, rather than a second copy of the component-name rule.
 *
 * A layout effect so the class lands in the same commit as the page swap,
 * before the browser paints.
 */
export function useSurface(surface: 'admin' | 'public'): void {
    useIsomorphicLayoutEffect(() => {
        document.documentElement.classList.toggle(
            'public-surface',
            surface === 'public',
        );
    }, [surface]);
}
