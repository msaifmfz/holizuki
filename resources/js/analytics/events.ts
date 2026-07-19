type EventParameters = {
    page_view: {
        page_location: string;
        page_referrer?: string;
        page_title?: string;
        content_key?: string;
        category_slug?: string;
        word_count_band?: string;
        publication_age_band?: string;
    };
    article_progress: { content_key: string; percent: 25 | 50 | 75 | 90 };
    article_engaged: { content_key: string };
    select_content: {
        content_type: 'article';
        item_id: string;
        content_source: string;
        content_location: string;
        content_key?: string;
        source_content_key?: string;
    };
    share: {
        method: string;
        content_type: 'article';
        item_id: string;
        content_key: string;
    };
    copy_code: {
        content_key: string;
        language: string;
        content_location: string;
    };
    comment_submit: { content_key: string };
    sign_up: {
        method: 'newsletter';
        content_key?: string;
        source_content_key?: string;
    };
    click: { link_domain: string; link_path: string };
    file_download: {
        link_domain: string;
        link_path: string;
        file_extension: string;
    };
};

export type AnalyticsEventName = keyof EventParameters;

const allowedParameterKeys: {
    [Name in AnalyticsEventName]: ReadonlyArray<keyof EventParameters[Name]>;
} = {
    page_view: [
        'page_location',
        'page_referrer',
        'page_title',
        'content_key',
        'category_slug',
        'word_count_band',
        'publication_age_band',
    ],
    article_progress: ['content_key', 'percent'],
    article_engaged: ['content_key'],
    select_content: [
        'content_type',
        'item_id',
        'content_source',
        'content_location',
        'content_key',
        'source_content_key',
    ],
    share: ['method', 'content_type', 'item_id', 'content_key'],
    copy_code: ['content_key', 'language', 'content_location'],
    comment_submit: ['content_key'],
    sign_up: ['method', 'content_key', 'source_content_key'],
    click: ['link_domain', 'link_path'],
    file_download: ['link_domain', 'link_path', 'file_extension'],
};

const piiKeyPattern = /(email|name|body|comment|password|query|search|text)/i;
const emailPattern = /\b[^\s@]+@[^\s@]+\.[^\s@]+\b/;
const sanitizedUrlKeys = new Set([
    'page_location',
    'page_referrer',
    'link_path',
]);

export function validateAnalyticsEvent<Name extends AnalyticsEventName>(
    name: Name,
    parameters: EventParameters[Name],
): boolean {
    if (!Object.hasOwn(allowedParameterKeys, name)) {
        return false;
    }

    const allowed = allowedParameterKeys[name] as readonly string[];

    for (const [key, value] of Object.entries(parameters)) {
        if (!allowed.includes(key) || piiKeyPattern.test(key)) {
            return false;
        }

        if (typeof value === 'string') {
            if (
                value.length > 256 ||
                emailPattern.test(value) ||
                /[\r\n]/.test(value) ||
                (sanitizedUrlKeys.has(key) &&
                    (value.includes('?') || value.includes('#')))
            ) {
                return false;
            }
        } else if (typeof value !== 'number' && typeof value !== 'boolean') {
            return false;
        }
    }

    return true;
}

export function sanitizedUrl(value: string, base = window.location.origin) {
    const url = new URL(value, base);

    return `${url.origin}${url.pathname}`;
}

export function sanitizedPath(value: string, base = window.location.origin) {
    return new URL(value, base).pathname;
}

export type AnalyticsEventParameters<Name extends AnalyticsEventName> =
    EventParameters[Name];
