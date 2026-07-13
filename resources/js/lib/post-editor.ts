export function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

export function toLocalDateTimeInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export function localDateTimeToUtc(value: string): string {
    return new Date(value).toISOString();
}

export function errorText(
    error: string | string[] | undefined,
): string | undefined {
    return Array.isArray(error) ? error[0] : error;
}

export function formatDate(
    value: string | null,
    dateStyle: 'medium' | 'long' = 'medium',
): string {
    return value
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle,
              timeStyle: 'short',
          }).format(new Date(value))
        : 'just now';
}
