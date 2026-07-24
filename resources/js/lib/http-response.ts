/**
 * Inertia's standalone HTTP client exposes exception response bodies as raw
 * text. Tests and custom clients may provide an already-decoded object, so
 * normalize both forms without ever surfacing malformed proxy/HTML bodies.
 */
export function decodeHttpResponsePayload(
    data: unknown,
): Record<string, unknown> {
    let decoded: unknown = data;

    if (typeof data === 'string') {
        try {
            decoded = JSON.parse(data);
        } catch {
            return {};
        }
    }

    return isRecord(decoded) ? decoded : {};
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
