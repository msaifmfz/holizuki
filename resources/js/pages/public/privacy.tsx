import { Head, usePage } from '@inertiajs/react';
import { openPrivacyManager } from '@/analytics/consent';

export default function Privacy() {
    const { analytics } = usePage().props;

    return (
        <>
            <Head title="Privacy Policy" />
            <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-4">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Legal
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                        Privacy Policy
                    </h1>
                </header>
                <div className="grid gap-8 text-base leading-7 text-foreground/90">
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            What this site collects
                        </h2>
                        <p>
                            Reading this blog requires no account. If you create
                            a Reader account, we store your fixed public display
                            name, email address, password hash, and verification
                            status to support commenting and account recovery.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Optional Google Analytics
                        </h2>
                        <p>
                            Google Analytics 4 is strictly opt-in. Before you
                            accept, this site does not load Google’s tag, create
                            GA cookies, or send cookieless pings. If accepted,
                            we send sanitized page paths without query strings
                            or fragments and a small allowlisted set of reading,
                            sharing, navigation, download, subscription, and
                            comment-submission events. Comment text, names,
                            email addresses, search queries, and other identity
                            are never included.
                        </p>
                        <p>
                            Google event data is retained for 14 months. Local
                            aggregate reports and achieved milestones may be
                            kept indefinitely. Both accept and decline choices
                            expire after {analytics.consentDays} days. You can
                            withdraw at any time; withdrawal stops further
                            events, removes known GA cookies, and reloads the
                            page.
                        </p>
                        <button
                            type="button"
                            onClick={openPrivacyManager}
                            className="w-fit font-medium underline underline-offset-4"
                        >
                            Review privacy choices
                        </button>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            The contact form
                        </h2>
                        <p>
                            If you use the contact form, the name, email
                            address, and message you submit are stored so we can
                            read and reply to it. Your IP address and browser
                            identifier are recorded alongside the submission to
                            prevent abuse. This information is never sold or
                            shared, and is deleted once it is no longer needed.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Comments
                        </h2>
                        <p>
                            Verified Readers may submit plain-text comments.
                            Your chosen display name and approved comment are
                            public; pending and rejected comments remain
                            private. Every comment is moderated. Rejected
                            comments are retained for up to 90 days, deleted
                            comment bodies for up to 30 days, and approved
                            comments until deletion.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Newsletter subscriptions
                        </h2>
                        <p>
                            Newsletter email addresses are encrypted at rest and
                            paired with a normalized one-way HMAC for exact
                            lookup and suppression. Confirmation and unsubscribe
                            tokens are stored only as hashes. Unconfirmed
                            subscriptions expire after seven days. Unsubscribing
                            immediately erases the encrypted address while
                            retaining the suppression hash.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Anonymous post popularity
                        </h2>
                        <p>
                            After a post has been visible for ten seconds, the
                            site records one anonymous first-party view for that
                            post per session each day. The record uses a daily
                            one-way session signature and does not store your IP
                            address, browser details, or a reusable visitor
                            identifier. View records are kept for 90 days and
                            are used only to surface popular writing.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Cookies
                        </h2>
                        <p>
                            The site sets a small number of first-party cookies:
                            a session cookie required for the site to function,
                            and a preference cookie remembering your light or
                            dark theme choice. Google Analytics cookies are
                            possible only after explicit acceptance and can be
                            withdrawn through Privacy choices in the footer.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Questions
                        </h2>
                        <p>
                            For any privacy question or request — including
                            asking for a contact submission to be deleted —
                            reach out through the contact page.
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}
