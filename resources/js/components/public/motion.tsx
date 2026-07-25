import { motion } from 'motion/react';
import type { HTMLMotionProps, Variants } from 'motion/react';
import type { ReactNode } from 'react';

/**
 * Shared motion vocabulary for the public blog.
 *
 * One easing curve and one travel distance everywhere, so the whole site
 * accelerates the same way. The variants below are driven by motion's
 * animation pipeline, so `<MotionConfig reducedMotion="user">` in PublicLayout
 * drops their `y` and keeps the opacity fade — nothing here needs its own
 * media query. Note that the guarantee stops there: scroll-bound values
 * applied straight through `style` (the hero parallax in posts/show.tsx) never
 * reach that pipeline and have to opt out by hand.
 */

/** Gentle deceleration — fast out of the gate, long settle. */
const easeOut = [0.22, 1, 0.36, 1] as const;

const travel = 14;

export const revealVariants: Variants = {
    hidden: { opacity: 0, y: travel },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.55, ease: easeOut },
    },
};

/**
 * Parent for staggered children. The container itself does not animate; it
 * only schedules the `RevealItem`s beneath it.
 */
export const staggerVariants: Variants = {
    hidden: {},
    visible: {
        transition: { staggerChildren: 0.07, delayChildren: 0.04 },
    },
};

/** Fire once, slightly before the element is fully on screen. */
const viewport = { once: true, margin: '0px 0px -10% 0px' } as const;

type RevealProps = HTMLMotionProps<'div'> & { children: ReactNode };

/**
 * A single element that fades up when scrolled into view.
 *
 * `as` only accepts the elements this is actually wrapped around, so a reveal
 * never silently downgrades an <article> to a <div>.
 */
export function Reveal({
    as = 'div',
    children,
    ...props
}: RevealProps & { as?: 'article' | 'div' | 'section' }) {
    const Component = motion[as];

    return (
        <Component
            initial="hidden"
            whileInView="visible"
            viewport={viewport}
            variants={revealVariants}
            data-reveal
            {...props}
        >
            {children}
        </Component>
    );
}

/**
 * Wraps a list so its `RevealItem` children arrive in sequence rather than
 * all at once. Renders a plain div — pass the grid classes through.
 */
export function RevealGroup({ children, ...props }: RevealProps) {
    return (
        <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={viewport}
            variants={staggerVariants}
            data-reveal
            {...props}
        >
            {children}
        </motion.div>
    );
}

/**
 * A child of `RevealGroup`. Inherits the parent's stagger schedule, so it
 * takes no viewport props of its own.
 */
export function RevealItem({
    children,
    ...props
}: HTMLMotionProps<'article'> & { children: ReactNode }) {
    return (
        <motion.article data-reveal variants={revealVariants} {...props}>
            {children}
        </motion.article>
    );
}
