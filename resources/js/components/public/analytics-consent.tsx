import { usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import {
    ANALYTICS_CONSENT_EVENT,
    clearGoogleAnalyticsCookies,
    OPEN_PRIVACY_MANAGER_EVENT,
    readAnalyticsConsent,
    saveAnalyticsConsent,
} from '@/analytics/consent';
import type {
    AnalyticsConsent,
    AnalyticsConsentStatus,
} from '@/analytics/consent';
import { denyLoadedAnalytics } from '@/analytics/tracker';
import { Button } from '@/components/ui/button';

const subscribeToHydration = () => () => undefined;

export default function AnalyticsConsentManager() {
    const { analytics } = usePage().props;
    const [consent, setConsent] = useState<AnalyticsConsent | null>(() =>
        readAnalyticsConsent(analytics.consentVersion),
    );
    const hydrated = useSyncExternalStore(
        subscribeToHydration,
        () => true,
        () => false,
    );
    const [managerOpen, setManagerOpen] = useState(false);

    useEffect(() => {
        const handleConsent = (event: Event) => {
            setConsent((event as CustomEvent<AnalyticsConsent>).detail);
        };
        const openManager = () => setManagerOpen(true);
        window.addEventListener(ANALYTICS_CONSENT_EVENT, handleConsent);
        window.addEventListener(OPEN_PRIVACY_MANAGER_EVENT, openManager);

        return () => {
            window.removeEventListener(ANALYTICS_CONSENT_EVENT, handleConsent);
            window.removeEventListener(OPEN_PRIVACY_MANAGER_EVENT, openManager);
        };
    }, [analytics.consentVersion]);

    if (
        !hydrated ||
        !analytics.collectionEnabled ||
        (consent && !managerOpen)
    ) {
        return null;
    }

    const decide = (status: AnalyticsConsentStatus) => {
        const next = saveAnalyticsConsent(
            analytics.consentVersion,
            status,
            analytics.consentDays,
        );
        setConsent(next);
        setManagerOpen(false);

        if (status === 'declined' && consent?.status === 'accepted') {
            denyLoadedAnalytics();
            clearGoogleAnalyticsCookies();
            window.location.reload();
        }
    };

    return (
        <section
            role={managerOpen ? 'dialog' : 'region'}
            aria-modal={managerOpen ? true : undefined}
            aria-labelledby="analytics-consent-title"
            // Sits on the night band rather than the page surface: a consent
            // notice is chrome over the article, and the constant dark ground
            // separates it from the reading without a drop shadow doing the work.
            className="band-night fixed right-3 bottom-3 left-3 z-[90] mx-auto grid max-w-3xl gap-5 border border-night-muted/30 p-5 sm:grid-cols-[1fr_auto] sm:items-center"
        >
            <div className="grid gap-2">
                <h2
                    id="analytics-consent-title"
                    className="eyebrow flex items-center gap-2 text-night-accent"
                >
                    <ShieldCheck className="size-3.5" aria-hidden />
                    Privacy choices
                </h2>
                <p className="font-body text-sm leading-relaxed text-night-muted">
                    With your permission, privacy-limited Google Analytics helps
                    us understand which writing is useful. Nothing is loaded
                    until you accept, and declining works for{' '}
                    {analytics.consentDays} days too.
                </p>
            </div>
            <div className="flex flex-wrap gap-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    data-testid="analytics-decline"
                    onClick={() => decide('declined')}
                    className="micro-cta h-10 rounded-none border-night-muted/40 bg-transparent px-4 text-night-foreground hover:border-night-foreground hover:bg-transparent hover:text-night-foreground"
                >
                    {consent?.status === 'accepted'
                        ? 'Withdraw consent'
                        : 'Decline'}
                </Button>
                <Button
                    type="button"
                    data-testid="analytics-accept"
                    onClick={() => decide('accepted')}
                    className="micro-cta h-10 rounded-none bg-night-accent px-4 text-night hover:bg-night-accent/90"
                >
                    Accept analytics
                </Button>
                {managerOpen && consent && (
                    <Button
                        type="button"
                        variant="ghost"
                        data-testid="analytics-cancel"
                        onClick={() => setManagerOpen(false)}
                        className="micro-cta h-10 rounded-none px-3 text-night-muted hover:bg-transparent hover:text-night-foreground"
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </section>
    );
}
