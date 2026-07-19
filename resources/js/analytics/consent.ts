export type AnalyticsConsentStatus = 'accepted' | 'declined';

export type AnalyticsConsent = {
    version: string;
    status: AnalyticsConsentStatus;
    decidedAt: string;
    expiresAt: string;
};

export const ANALYTICS_CONSENT_KEY = 'holizuki.analytics-consent';
export const ANALYTICS_CONSENT_EVENT = 'holizuki:analytics-consent';
export const OPEN_PRIVACY_MANAGER_EVENT = 'holizuki:open-privacy-manager';

export function readAnalyticsConsent(
    version: string,
    now = new Date(),
): AnalyticsConsent | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const stored = window.localStorage.getItem(ANALYTICS_CONSENT_KEY);

        if (!stored) {
            return null;
        }

        const consent = JSON.parse(stored) as Partial<AnalyticsConsent>;
        const validStatus =
            consent.status === 'accepted' || consent.status === 'declined';
        const expiresAt =
            typeof consent.expiresAt === 'string'
                ? new Date(consent.expiresAt)
                : null;

        if (
            consent.version !== version ||
            !validStatus ||
            !expiresAt ||
            Number.isNaN(expiresAt.getTime()) ||
            expiresAt <= now
        ) {
            window.localStorage.removeItem(ANALYTICS_CONSENT_KEY);

            return null;
        }

        return consent as AnalyticsConsent;
    } catch {
        window.localStorage.removeItem(ANALYTICS_CONSENT_KEY);

        return null;
    }
}

export function saveAnalyticsConsent(
    version: string,
    status: AnalyticsConsentStatus,
    days: number,
    now = new Date(),
): AnalyticsConsent {
    const consent: AnalyticsConsent = {
        version,
        status,
        decidedAt: now.toISOString(),
        expiresAt: new Date(
            now.getTime() + days * 24 * 60 * 60 * 1000,
        ).toISOString(),
    };

    window.localStorage.setItem(ANALYTICS_CONSENT_KEY, JSON.stringify(consent));
    window.dispatchEvent(
        new CustomEvent<AnalyticsConsent>(ANALYTICS_CONSENT_EVENT, {
            detail: consent,
        }),
    );

    return consent;
}

export function clearGoogleAnalyticsCookies() {
    if (typeof document === 'undefined') {
        return;
    }

    const names = document.cookie
        .split(';')
        .map((cookie) => cookie.trim().split('=')[0])
        .filter(
            (name) =>
                name === '_ga' ||
                name === '_gid' ||
                name === '_gat' ||
                name.startsWith('_ga_'),
        );
    const hostname = window.location.hostname;
    const labels = hostname.split('.');
    const domains = [''];

    // GA may set its cookie on any registrable parent domain; multi-label
    // public suffixes (e.g. co.uk) make eTLD+1 unguessable without a suffix
    // list, so attempt every parent — browsers ignore public-suffix clears.
    for (let index = 0; index < Math.max(1, labels.length - 1); index++) {
        const domain = labels.slice(index).join('.');
        domains.push(domain, `.${domain}`);
    }

    for (const name of names) {
        for (const domain of domains) {
            const domainPart = domain === '' ? '' : `; domain=${domain}`;
            document.cookie = `${name}=; Max-Age=0; path=/${domainPart}; SameSite=Lax`;
        }
    }
}

export function openPrivacyManager() {
    window.dispatchEvent(new Event(OPEN_PRIVACY_MANAGER_EVENT));
}
