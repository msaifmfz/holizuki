/**
 * The moon phase on the night a post was published.
 *
 * The site is named for tsuki (月), and the about page pins its thesis to the
 * hour you arrive somewhere new — so the phase of the moon on a post's own
 * publication night is the mark that runs through the design, in place of the
 * decorative dot it replaces.
 *
 * Everything here is a pure function of the ISO string: no `Date.now()`, no
 * `Intl`, no local time zone. SSR renders the same glyph the browser does, so
 * these never cause a hydration mismatch.
 *
 * The maths is Meeus, *Astronomical Algorithms*, chapter 48 — the phase angle
 * (48.4) and the illuminated fraction (48.1), truncated to the terms that
 * matter at this scale. Measured against the published syzygies and quarters in
 * the tests, it lands within ~25 minutes of each, which is far tighter than the
 * drawn disc can express. A mean-synodic-month approximation would have been a
 * quarter of the code but drifts up to ~14 hours, and that *is* visible as a
 * wrong-looking crescent near the quarters, where illumination moves fastest.
 */

const RAD = Math.PI / 180;

export type LunarPhase = {
    /**
     * Position in the synodic cycle: 0 new → 0.25 first quarter → 0.5 full →
     * 0.75 last quarter → 1 new again.
     */
    cycle: number;
    /** Illuminated fraction of the visible disc, 0 (new) to 1 (full). */
    illumination: number;
    /** Waxing moons are lit on the right from the northern hemisphere. */
    waxing: boolean;
    /** Lower-case label, e.g. `waxing gibbous`. */
    name: string;
};

/**
 * Resolve the lunar phase for an ISO 8601 timestamp, or `null` if the value is
 * absent or unparseable — `published_at` is nullable on every post DTO, and a
 * bad timestamp should drop the mark rather than take the page down.
 */
export function lunarPhase(
    value: string | null | undefined,
): LunarPhase | null {
    if (!value) {
        return null;
    }

    const time = new Date(value).getTime();

    if (Number.isNaN(time)) {
        return null;
    }

    // Julian Day from the Unix epoch, which is JD 2440587.5.
    const julianDay = time / 86_400_000 + 2_440_587.5;
    const centuries = (julianDay - 2_451_545) / 36_525;

    // Mean elongation of the Moon from the Sun. This is what carries the cycle
    // position: 0° is new, 90° first quarter, 180° full, 270° last quarter.
    const elongation = 297.850_192_1 + 445_267.111_403_4 * centuries;
    // Sun's mean anomaly.
    const sunAnomaly = 357.529_109_2 + 35_999.050_290_9 * centuries;
    // Moon's mean anomaly.
    const moonAnomaly = 134.963_396_4 + 477_198.867_505_5 * centuries;

    const d = normalizeDegrees(elongation);
    const m = sunAnomaly * RAD;
    const mp = moonAnomaly * RAD;
    const dRad = d * RAD;

    // Meeus 48.4 — the Sun–Earth–Moon phase angle, 0° at full and 180° at new.
    const phaseAngle =
        180 -
        d -
        6.289 * Math.sin(mp) +
        2.1 * Math.sin(m) -
        1.274 * Math.sin(2 * dRad - mp) -
        0.658 * Math.sin(2 * dRad) -
        0.214 * Math.sin(2 * mp) -
        0.11 * Math.sin(dRad);

    // The true phase angle is in [0°, 180°] — 0 at full, 180 at new. The
    // formula above can land outside that, so fold it back.
    const folded = normalizeDegrees(phaseAngle);
    const angle = folded > 180 ? 360 - folded : folded;

    // Waxing versus waning is the one thing the phase angle cannot tell us: it
    // is symmetric about full, so a waxing and a waning crescent share an
    // angle. Mean elongation supplies the direction. It can disagree with the
    // corrected angle by a fraction of a day, but only ever within a few hours
    // of new or full, where the disc looks identical either way.
    const waxing = d < 180;

    // Derive the cycle position from the *corrected* angle rather than from
    // mean elongation. Taking it from `d` was wrong: it drifts up to half a day
    // against the corrected illumination, which put the name and the drawn
    // glyph out of step near the quarters.
    const cycle = normalizeDegrees(waxing ? 180 - angle : 180 + angle) / 360;

    return {
        cycle,
        // Meeus 48.1.
        illumination: (1 + Math.cos(angle * RAD)) / 2,
        waxing,
        name: phaseName(cycle),
    };
}

/**
 * The eight conventional phase names, each centred on its exact moment: `new`
 * covers the eighth of the cycle either side of 0, `first quarter` the eighth
 * either side of 0.25, and so on.
 */
function phaseName(cycle: number): string {
    const eighth = Math.round(cycle * 8) % 8;

    return [
        'new moon',
        'waxing crescent',
        'first quarter',
        'waxing gibbous',
        'full moon',
        'waning gibbous',
        'last quarter',
        'waning crescent',
    ][eighth];
}

/** Fold an angle in degrees into [0, 360). */
function normalizeDegrees(degrees: number): number {
    return ((degrees % 360) + 360) % 360;
}
