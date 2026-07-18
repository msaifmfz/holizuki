import { Head } from '@inertiajs/react';

export default function Terms() {
    return (
        <>
            <Head title="Terms of Use" />
            <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-4">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Legal
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                        Terms of Use
                    </h1>
                </header>
                <div className="grid gap-8 text-base leading-7 text-foreground/90">
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            Content
                        </h2>
                        <p>
                            The writing on this site belongs to its authors. You
                            are welcome to quote and link to posts with
                            attribution; republishing a full post requires
                            permission first. Code snippets in posts may be used
                            freely in your own projects unless a post states
                            otherwise.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            No warranty
                        </h2>
                        <p>
                            Posts describe what worked for us at the time of
                            writing. Software moves quickly, and advice can go
                            stale — apply your own judgment before using
                            anything here in production. The site and its
                            content are provided “as is”, without warranty of
                            any kind.
                        </p>
                    </section>
                    <section className="grid gap-3">
                        <h2 className="font-display text-2xl font-semibold">
                            External links
                        </h2>
                        <p>
                            Posts link out to other sites. Those sites are not
                            under our control, and linking to them does not mean
                            we endorse everything they publish.
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}
