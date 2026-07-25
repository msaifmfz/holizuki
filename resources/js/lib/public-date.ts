/**
 * Reader-facing date formatting.
 *
 * Deliberately separate from `formatDate` in lib/post-editor, which appends a
 * timestamp — useful in the admin's revision list, noise in a byline.
 *
 * There used to be a `formatPublicDate` here that ran through
 * `new Intl.DateTimeFormat(undefined, …)`. It is gone: a locale-less `Intl`
 * resolves to the *renderer's* locale, so under SSR the server produced
 * `Jun 15, 2026` and a browser set to en-GB produced `15 June 2026` for the same
 * post — a hydration mismatch on every byline on the site. Everything reader-
 * facing now goes through `formatCompactDate`, which is a pure function of the
 * ISO string.
 */
const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
] as const;

/**
 * `15 Jun 2026` — the dateline format, sized for a tracked-caps meta row where
 * "June 15, 2026" would be too long and would wrap.
 *
 * Built from the UTC calendar date in the ISO string rather than through `Intl`,
 * for the same reason `wasUpdatedAfterPublishing` below slices UTC days: with
 * SSR on, the server and the browser resolve a locale-less `Intl` differently
 * (`Jun 15, 2026` versus `15 Jun 2026`) and disagree about the day either side
 * of midnight. Both would surface as a hydration mismatch on every card.
 */
export function formatCompactDate(value: string | null): string {
    if (!value) {
        return '';
    }

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        return '';
    }

    const [, year, month, day] = match;
    const name = MONTHS[Number(month) - 1];

    if (!name) {
        return '';
    }

    return `${Number(day)} ${name} ${year}`;
}

/**
 * True when a post has been meaningfully revised since publication, so the
 * byline can say so. Same-day edits are treated as part of publishing.
 *
 * Compares the UTC calendar date carried in the ISO strings rather than local
 * days: with SSR on, the server and the browser sit in different time zones,
 * and a `Date`-based comparison would disagree about edits near midnight and
 * produce a hydration mismatch.
 */
export function wasUpdatedAfterPublishing(
    publishedAt: string | null,
    updatedAt: string | null,
): boolean {
    if (!publishedAt || !updatedAt) {
        return false;
    }

    return utcDay(updatedAt) !== utcDay(publishedAt);
}

/** The `YYYY-MM-DD` portion of an ISO 8601 timestamp. */
function utcDay(value: string): string {
    return value.slice(0, 10);
}
