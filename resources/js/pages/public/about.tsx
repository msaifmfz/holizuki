import { Head } from '@inertiajs/react';

export default function About() {
    return (
        <>
            <Head title="About" />
            <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-4">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        About
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                        A field journal for building software
                    </h1>
                </header>
                <div className="grid gap-5 text-base leading-7 text-foreground/90">
                    <p>
                        Holizuki is a blog about the craft of building for the
                        web: software design, product decisions, and the details
                        that separate work that ships from work that lasts.
                    </p>
                    <p>
                        Posts here are written from practice, not theory —
                        drafts get revised, scheduled, and published from the
                        same platform this site runs on. Expect writing about
                        Laravel, React, developer workflows, and the occasional
                        detour into product thinking.
                    </p>
                    <p>
                        The name comes from <em>tsuki</em> (月), the moon: most
                        of this writing happens after dark. If you want to get
                        in touch about a post, an idea, or a correction, the
                        contact page is the fastest route.
                    </p>
                </div>
            </div>
        </>
    );
}
