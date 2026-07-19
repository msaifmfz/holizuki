import { readAnalyticsConsent } from '@/analytics/consent';
import type { AnalyticsConsent } from '@/analytics/consent';
import {
    sanitizedPath,
    sanitizedUrl,
    validateAnalyticsEvent,
} from '@/analytics/events';
import type {
    AnalyticsEventName,
    AnalyticsEventParameters,
} from '@/analytics/events';

type AnalyticsRuntime = {
    collectionEnabled: boolean;
    measurementId: string | null;
    consentVersion: string;
    eligible: boolean;
};

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: (...arguments_: unknown[]) => void;
    }
}

let runtime: AnalyticsRuntime = {
    collectionEnabled: false,
    measurementId: null,
    consentVersion: '',
    eligible: false,
};
let loadedMeasurementId: string | null = null;

export function configureAnalytics(next: AnalyticsRuntime) {
    runtime = next;
}

function analyticsConsent(): AnalyticsConsent | null {
    return readAnalyticsConsent(runtime.consentVersion);
}

function canSendAnalytics() {
    return (
        runtime.collectionEnabled &&
        runtime.eligible &&
        typeof runtime.measurementId === 'string' &&
        runtime.measurementId !== '' &&
        analyticsConsent()?.status === 'accepted'
    );
}

export function ensureGoogleAnalyticsLoaded() {
    if (!canSendAnalytics() || typeof document === 'undefined') {
        return false;
    }

    const measurementId = runtime.measurementId;

    if (!measurementId) {
        return false;
    }

    if (loadedMeasurementId === measurementId && window.gtag) {
        return true;
    }

    window.dataLayer = window.dataLayer ?? [];
    window.gtag = (...arguments_: unknown[]) => {
        window.dataLayer?.push(arguments_);
    };
    window.gtag('consent', 'default', {
        analytics_storage: 'granted',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    window.gtag('js', new Date());
    window.gtag('config', measurementId, {
        send_page_view: false,
        allow_google_signals: false,
        allow_ad_personalization_signals: false,
    });

    const script = document.createElement('script');
    script.id = 'holizuki-ga4';
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.append(script);
    loadedMeasurementId = measurementId;

    return true;
}

export function trackAnalyticsEvent<Name extends AnalyticsEventName>(
    name: Name,
    parameters: AnalyticsEventParameters<Name>,
) {
    const sanitizedParameters = removeUndefined(parameters);

    if (
        !validateAnalyticsEvent(name, sanitizedParameters) ||
        !ensureGoogleAnalyticsLoaded() ||
        !window.gtag
    ) {
        return false;
    }

    window.gtag('event', name, sanitizedParameters);

    return true;
}

export function denyLoadedAnalytics() {
    window.gtag?.('consent', 'update', {
        analytics_storage: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    runtime = { ...runtime, eligible: false };
}

export function isAnalyticsEligible(path: string, role?: string | null) {
    if (role === 'administrator') {
        return false;
    }

    const pathname = sanitizedPath(path, 'http://localhost');
    const exactExcluded = [
        '/account',
        '/confirm-password',
        '/login',
        '/register',
        '/forgot-password',
        '/two-factor-challenge',
        '/verify-email',
        '/dashboard',
        '/newsletter/unsubscribed',
    ];
    const excludedPrefixes = [
        '/account/',
        '/dashboard/',
        '/settings',
        '/community/',
        '/email/',
        '/email/verify/',
        '/reset-password/',
        '/newsletter/confirm/',
        '/newsletter/unsubscribe/',
    ];

    return (
        !exactExcluded.includes(pathname) &&
        !excludedPrefixes.some((prefix) => pathname.startsWith(prefix)) &&
        !pathname.endsWith('/preview')
    );
}

export function pageViewParameters(
    pageUrl: string,
    title: string,
    post?: {
        id?: number;
        category?: { slug?: string } | null;
        word_count_band?: string;
        published_at?: string | null;
    },
) {
    const parameters: AnalyticsEventParameters<'page_view'> = {
        page_location: sanitizedUrl(pageUrl),
        page_title: title,
    };

    if (document.referrer) {
        parameters.page_referrer = sanitizedUrl(document.referrer);
    }

    if (typeof post?.id === 'number') {
        parameters.content_key = `post:${post.id}`;
        parameters.category_slug = post.category?.slug;
        parameters.word_count_band = post.word_count_band;
        parameters.publication_age_band = publicationAgeBand(post.published_at);
    }

    return removeUndefined(parameters);
}

function publicationAgeBand(publishedAt?: string | null) {
    if (!publishedAt) {
        return 'unknown';
    }

    const ageDays = Math.max(
        0,
        Math.floor((Date.now() - new Date(publishedAt).getTime()) / 86_400_000),
    );

    if (ageDays < 7) {
        return 'under_7_days';
    }

    if (ageDays < 30) {
        return '7_29_days';
    }

    if (ageDays < 180) {
        return '30_179_days';
    }

    return '180_days_plus';
}

function removeUndefined<Name extends AnalyticsEventName>(
    parameters: AnalyticsEventParameters<Name>,
) {
    return Object.fromEntries(
        Object.entries(parameters).filter(([, value]) => value !== undefined),
    ) as AnalyticsEventParameters<Name>;
}

export function resetAnalyticsForTests() {
    runtime = {
        collectionEnabled: false,
        measurementId: null,
        consentVersion: '',
        eligible: false,
    };
    loadedMeasurementId = null;
}
