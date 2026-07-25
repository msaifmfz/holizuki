import MoonPhase from '@/components/public/moon-phase';
import { formatCompactDate } from '@/lib/public-date';
import { cn } from '@/lib/utils';
import type { PublicPostDetail } from '@/types';

/**
 * The meta line under or over a title: the moon on the night of publication,
 * the date, how long the piece takes, and whether it is a long one.
 *
 * This is where the phase mark earns its place — it sits against the date it
 * describes, so the disc is a reading of the timestamp rather than an ornament.
 * Everything is set in tracked narrow caps with tabular figures, so a column of
 * these lines up down the page.
 */

/**
 * Only the top two bands get a label. `1000_1499` is a normal-length post, and
 * flagging it would make the chip meaningless.
 */
const LONG_READ_BANDS: ReadonlySet<PublicPostDetail['word_count_band']> =
    new Set(['1500_2499', '2500_plus']);

export default function Dateline({
    date,
    readingTime,
    wordCountBand,
    phaseSize = 11,
    labelPhase = false,
    className,
}: {
    date: string | null;
    readingTime?: number;
    wordCountBand?: PublicPostDetail['word_count_band'];
    /** Rendered size of the phase disc in px. */
    phaseSize?: number;
    /** Announce the phase to assistive tech — for the one large mark per page. */
    labelPhase?: boolean;
    className?: string;
}) {
    const formatted = formatCompactDate(date);
    const isLongRead = wordCountBand
        ? LONG_READ_BANDS.has(wordCountBand)
        : false;

    return (
        <div
            className={cn(
                'eyebrow flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-muted-foreground',
                className,
            )}
        >
            <MoonPhase
                date={date}
                size={phaseSize}
                label={labelPhase}
                className="text-brand-accent"
            />

            {formatted && (
                <time dateTime={date ?? undefined} className="meta-num">
                    {formatted}
                </time>
            )}

            {readingTime !== undefined && (
                <>
                    {formatted && <Separator />}
                    {/* Reads as "6 MIN" in the tracked-caps row, which is as much
                        as the meta line has room for, but announces "6 min read"
                        — on its own, next to a date, "6 min" is ambiguous. */}
                    <span className="meta-num">
                        {readingTime} min<span className="sr-only"> read</span>
                    </span>
                </>
            )}

            {isLongRead && (
                <>
                    <Separator />
                    <span className="text-primary">Long read</span>
                </>
            )}
        </div>
    );
}

function Separator() {
    return (
        <span aria-hidden className="text-border">
            /
        </span>
    );
}
