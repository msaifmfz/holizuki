import { lunarPhase } from '@/lib/lunar-phase';
import { cn } from '@/lib/utils';

/**
 * The moon as it stood on the night a post was published.
 *
 * Drawn as an almanac mark rather than a picture of the moon: the lit portion is
 * filled ink and the rest is an outlined disc. A photographic treatment — pale
 * disc on dark sky — would vanish against the paper in light mode, whereas ink
 * reads on both grounds and suits the logbook voice the rest of the meta uses.
 *
 * The terminator is a true ellipse, not a clipped circle. Its semi-minor axis is
 * r·|2k − 1| for illuminated fraction k, which collapses to r at both new and
 * full — so the same path renders an empty outline, a half disc, and a solid
 * disc without special-casing any of them.
 */
export default function MoonPhase({
    date,
    size = 10,
    label,
    className,
}: {
    date: string | null;
    /** Rendered edge length in px. */
    size?: number;
    /**
     * Announce the phase to assistive tech. Off by default — a listing page
     * carries a dozen of these next to the date they already describe, and
     * reading "waxing gibbous" before every headline is noise.
     */
    label?: boolean;
    className?: string;
}) {
    const phase = lunarPhase(date);

    if (!phase) {
        return null;
    }

    const radius = 49;
    const { illumination, waxing } = phase;

    // The limb runs down whichever side is lit; the terminator returns along the
    // ellipse. Sweep flags decide whether that return bulges into the dark half
    // (gibbous) or back across the lit one (crescent).
    const limbSweep = waxing ? 1 : 0;
    const terminatorSweep = waxing === illumination >= 0.5 ? 1 : 0;
    // Collapses to `radius` at both new and full, which is what lets one path
    // cover the empty outline, the half disc, and the solid disc.
    const terminatorRadius = radius * Math.abs(2 * illumination - 1);

    const lit = [
        `M 50 ${50 - radius}`,
        `A ${radius} ${radius} 0 0 ${limbSweep} 50 ${50 + radius}`,
        `A ${terminatorRadius} ${radius} 0 0 ${terminatorSweep} 50 ${50 - radius}`,
        'Z',
    ].join(' ');

    return (
        <svg
            viewBox="0 0 100 100"
            width={size}
            height={size}
            className={cn('shrink-0', className)}
            {...(label
                ? { role: 'img', 'aria-label': `Published on a ${phase.name}` }
                : { 'aria-hidden': true })}
        >
            <circle
                cx="50"
                cy="50"
                r={radius}
                fill="none"
                stroke="currentColor"
                strokeWidth="1"
                strokeOpacity="0.45"
                // Keeps the outline a hairline whether this renders at 10px in a
                // byline or 120px over the home hero.
                vectorEffect="non-scaling-stroke"
            />
            <path d={lit} fill="currentColor" />
        </svg>
    );
}
