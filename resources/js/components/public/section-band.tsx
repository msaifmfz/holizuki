import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * A full-bleed section: colour runs edge to edge, content stays on the grid.
 *
 * There is no negative-margin escape hack here. Public pages are laid out
 * full-width at the root and every section owns its own centred container, so a
 * band only has to paint a background. The `margin-left: calc(50% - 50vw)`
 * trick would have let bands sit inside a page-level container, but 100vw
 * includes the scrollbar and the page ends up scrolling sideways by ~15px.
 *
 * `tone`:
 *   `paper` — a tinted step away from the page, for a quiet change of texture.
 *   `night` — the constant dark ground. Its ink does not track light/dark mode,
 *     so use `text-night-*` inside rather than the surface tokens.
 */
export default function SectionBand({
    tone = 'paper',
    className,
    innerClassName,
    children,
    ...props
}: {
    tone?: 'paper' | 'night';
    className?: string;
    innerClassName?: string;
    children: ReactNode;
} & Omit<React.ComponentPropsWithoutRef<'section'>, 'className' | 'children'>) {
    return (
        <section
            className={cn(tone === 'night' ? 'band-night' : 'band', className)}
            {...props}
        >
            <div
                className={cn(
                    'mx-auto w-full max-w-6xl px-4 py-14 sm:py-18',
                    innerClassName,
                )}
            >
                {children}
            </div>
        </section>
    );
}
