import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    ANALYTICS_CONSENT_EVENT,
    readAnalyticsConsent,
} from '@/analytics/consent';
import type { AnalyticsConsent } from '@/analytics/consent';
import { sanitizedPath } from '@/analytics/events';
import {
    configureAnalytics,
    isAnalyticsEligible,
    pageViewParameters,
    trackAnalyticsEvent,
} from '@/analytics/tracker';

let lastTrackedPath: string | null = null;
let lastCommentFlash: string | null = null;
let lastSignUpFlash: string | null = null;
let partialNavigationPending = false;

export function claimPageView(path: string) {
    if (lastTrackedPath === path) {
        return false;
    }

    lastTrackedPath = path;

    return true;
}

export function articleProgressPercent(
    bodyTop: number,
    bodyBottom: number,
    bodyHeight: number,
    viewportHeight: number,
) {
    if (bodyHeight <= 0) {
        return 0;
    }

    return Math.max(
        0,
        Math.min(
            100,
            ((Math.min(viewportHeight, bodyBottom) - bodyTop) / bodyHeight) *
                100,
        ),
    );
}

export default function PublicAnalytics() {
    const page = usePage();
    const { analytics, auth, flash } = page.props;
    const [consentStatus, setConsentStatus] = useState(
        () => readAnalyticsConsent(analytics.consentVersion)?.status ?? null,
    );
    const path = sanitizedPath(page.url, 'http://localhost');
    const navigationKey = page.url;
    const eligible = isAnalyticsEligible(page.url, auth.user?.role);
    const props = page.props as Record<string, unknown>;
    const post = props.post as
        | {
              id?: number;
              category?: { slug?: string } | null;
              word_count_band?: string;
              published_at?: string | null;
          }
        | undefined;

    useEffect(() => {
        configureAnalytics({
            collectionEnabled: analytics.collectionEnabled,
            measurementId: analytics.measurementId,
            consentVersion: analytics.consentVersion,
            eligible,
        });
    }, [
        analytics.collectionEnabled,
        analytics.measurementId,
        analytics.consentVersion,
        eligible,
    ]);

    useEffect(() => {
        return router.on('start', (event) => {
            partialNavigationPending =
                event.detail.visit.only.length > 0 ||
                event.detail.visit.except.length > 0;
        });
    }, []);

    useEffect(() => {
        const updateConsent = (event: Event) => {
            setConsentStatus(
                (event as CustomEvent<AnalyticsConsent>).detail.status,
            );
        };

        window.addEventListener(ANALYTICS_CONSENT_EVENT, updateConsent);

        return () =>
            window.removeEventListener(ANALYTICS_CONSENT_EVENT, updateConsent);
    }, []);

    useEffect(() => {
        if (lastTrackedPath === navigationKey) {
            return;
        }

        // Consume the partial flag even when consent is undecided, so a
        // partial reload can never suppress a later genuine page_view.
        const wasPartialNavigation = partialNavigationPending;
        partialNavigationPending = false;

        if (!eligible || consentStatus !== 'accepted') {
            return;
        }

        // A partial reload only skips tracking when the visit's base page
        // was already counted; otherwise (consent accepted mid-visit) the
        // current page still deserves its one page_view.
        if (wasPartialNavigation && lastTrackedPath !== null) {
            claimPageView(navigationKey);

            return;
        }

        if (
            trackAnalyticsEvent(
                'page_view',
                pageViewParameters(page.url, document.title, post),
            )
        ) {
            claimPageView(navigationKey);
        }
    }, [consentStatus, eligible, navigationKey, page.url, post]);

    useEffect(() => {
        if (
            !eligible ||
            consentStatus !== 'accepted' ||
            typeof post?.id !== 'number'
        ) {
            return;
        }

        const contentKey = `post:${post.id}`;
        const body = document.querySelector<HTMLElement>('[data-article-body]');

        if (!body) {
            return;
        }

        const sentThresholds = new Set<number>();
        let progress = 0;
        let activeSeconds = 0;
        let engaged = false;

        const measure = () => {
            const rect = body.getBoundingClientRect();
            progress = articleProgressPercent(
                rect.top,
                rect.bottom,
                rect.height,
                window.innerHeight,
            );

            for (const threshold of [25, 50, 75, 90] as const) {
                if (progress >= threshold && !sentThresholds.has(threshold)) {
                    if (
                        trackAnalyticsEvent('article_progress', {
                            content_key: contentKey,
                            percent: threshold,
                        })
                    ) {
                        sentThresholds.add(threshold);
                    }
                }
            }
        };

        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible' && document.hasFocus()) {
                activeSeconds += 1;
            }

            if (!engaged && activeSeconds >= 30 && progress >= 50) {
                engaged = trackAnalyticsEvent('article_engaged', {
                    content_key: contentKey,
                });
            }
        }, 1000);

        measure();
        window.addEventListener('scroll', measure, { passive: true });
        window.addEventListener('resize', measure);

        return () => {
            window.clearInterval(timer);
            window.removeEventListener('scroll', measure);
            window.removeEventListener('resize', measure);
        };
    }, [consentStatus, eligible, path, post?.id]);

    useEffect(() => {
        if (
            !eligible ||
            consentStatus !== 'accepted' ||
            typeof post?.id !== 'number'
        ) {
            return;
        }

        const flashKey = `${path}:${post.id}:${flash.commentSubmitted ?? ''}`;

        if (flash.commentSubmitted && lastCommentFlash !== flashKey) {
            if (
                trackAnalyticsEvent('comment_submit', {
                    content_key: `post:${post.id}`,
                })
            ) {
                lastCommentFlash = flashKey;
            }
        }
    }, [consentStatus, eligible, flash.commentSubmitted, path, post?.id]);

    useEffect(() => {
        const confirmed = props.subscriptionConfirmed === true;
        const sourceContentKey =
            typeof props.sourceContentKey === 'string'
                ? props.sourceContentKey
                : undefined;
        const flashKey = `${path}:${sourceContentKey ?? 'site'}`;

        if (
            eligible &&
            consentStatus === 'accepted' &&
            confirmed &&
            lastSignUpFlash !== flashKey
        ) {
            if (
                trackAnalyticsEvent('sign_up', {
                    method: 'newsletter',
                    content_key: sourceContentKey,
                    source_content_key: sourceContentKey,
                })
            ) {
                lastSignUpFlash = flashKey;
            }
        }
    }, [
        consentStatus,
        eligible,
        path,
        props.sourceContentKey,
        props.subscriptionConfirmed,
    ]);

    useEffect(() => {
        if (!eligible || consentStatus !== 'accepted') {
            return;
        }

        const handleClick = (event: MouseEvent) => {
            const anchor = (event.target as Element | null)?.closest('a');

            if (!(anchor instanceof HTMLAnchorElement) || !anchor.href) {
                return;
            }

            const destination = new URL(anchor.href, window.location.origin);
            const extension = destination.pathname
                .split('.')
                .pop()
                ?.toLowerCase();
            const isDownload =
                anchor.hasAttribute('download') ||
                (extension !== undefined &&
                    [
                        'pdf',
                        'zip',
                        'csv',
                        'doc',
                        'docx',
                        'xls',
                        'xlsx',
                    ].includes(extension));

            if (isDownload && extension) {
                trackAnalyticsEvent('file_download', {
                    link_domain: destination.host,
                    link_path: destination.pathname,
                    file_extension: extension,
                });
            } else if (destination.origin !== window.location.origin) {
                trackAnalyticsEvent('click', {
                    link_domain: destination.host,
                    link_path: destination.pathname,
                });
            } else if (
                anchor.dataset.contentKey?.startsWith('post:') &&
                anchor.dataset.contentSource &&
                anchor.dataset.contentLocation
            ) {
                trackAnalyticsEvent('select_content', {
                    content_type: 'article',
                    item_id: anchor.dataset.contentKey,
                    content_source: anchor.dataset.contentSource,
                    content_location: anchor.dataset.contentLocation,
                    content_key:
                        typeof post?.id === 'number'
                            ? `post:${post.id}`
                            : undefined,
                    source_content_key:
                        typeof post?.id === 'number'
                            ? `post:${post.id}`
                            : undefined,
                });
            }
        };

        document.addEventListener('click', handleClick, true);

        return () => document.removeEventListener('click', handleClick, true);
    }, [consentStatus, eligible, post?.id]);

    return null;
}

export function resetPublicAnalyticsForTests() {
    lastTrackedPath = null;
    lastCommentFlash = null;
    lastSignUpFlash = null;
    partialNavigationPending = false;
}
