import { Head } from '@inertiajs/react';

export default function Privacy() {
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
                            Reading this blog requires no account and no
                            personal information. The site does not run
                            third-party analytics or advertising trackers.
                        </p>
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
                            dark theme choice. No cross-site tracking cookies
                            are used.
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
