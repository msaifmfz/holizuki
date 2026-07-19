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
            className="fixed right-3 bottom-3 left-3 z-[90] mx-auto grid max-w-3xl gap-4 rounded-xl border bg-background p-5 shadow-2xl sm:grid-cols-[1fr_auto] sm:items-center"
        >
            <div className="grid gap-2">
                <h2
                    id="analytics-consent-title"
                    className="flex items-center gap-2 font-semibold"
                >
                    <ShieldCheck className="size-5" aria-hidden />
                    Privacy choices
                </h2>
                <p className="text-sm leading-6 text-muted-foreground">
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
                >
                    {consent?.status === 'accepted'
                        ? 'Withdraw consent'
                        : 'Decline'}
                </Button>
                <Button
                    type="button"
                    data-testid="analytics-accept"
                    onClick={() => decide('accepted')}
                >
                    Accept analytics
                </Button>
                {managerOpen && consent && (
                    <Button
                        type="button"
                        variant="ghost"
                        data-testid="analytics-cancel"
                        onClick={() => setManagerOpen(false)}
                    >
                        Cancel
                    </Button>
                )}
            </div>
        </section>
    );
}
