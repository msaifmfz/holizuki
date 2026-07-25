import { Head } from '@inertiajs/react';

export default function About() {
    return (
        <>
            <Head title="About" />
            <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-5 border-t border-border pt-5">
                    <p className="eyebrow text-brand-accent">About</p>
                    <h1 className="display-lg">
                        A travel journal for people who unpack slowly
                    </h1>
                </header>
                <div className="grid gap-6 font-body text-lg leading-relaxed text-foreground/90">
                    <p>
                        Holizuki is about going fewer places and staying longer.
                        Long walks, slow trains, the third coffee at the same
                        corner table — and the hotels, guesthouses, and rented
                        rooms worth rearranging a trip for.
                    </p>
                    <p>
                        Everything here is first-hand. We pay for our own rooms,
                        we say when a place isn&rsquo;t worth the money, and we
                        go back to the ones that are. Expect honest write-ups, a
                        few wrong turns, and directions specific enough to
                        actually follow.
                    </p>
                    <p>
                        The name comes from <em>tsuki</em> (月), the moon — the
                        best hour to arrive somewhere new, when the streets have
                        emptied and you still have to find your door. If you
                        want to get in touch about a place, a correction, or
                        somewhere we should go next, the contact page is the
                        fastest route.
                    </p>
                </div>
            </div>
        </>
    );
}
