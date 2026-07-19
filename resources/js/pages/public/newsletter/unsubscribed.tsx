import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

export default function Unsubscribed() {
    return (
        <>
            <Head title="Unsubscribed" />
            <div className="mx-auto grid max-w-xl gap-5 px-4 py-20 text-center">
                <h1 className="font-display text-3xl font-semibold">
                    You’re unsubscribed
                </h1>
                <p className="text-muted-foreground">
                    Your email address has been erased from the active mailing
                    list.
                </p>
                <Button asChild>
                    <Link href={home()}>Return home</Link>
                </Button>
            </div>
        </>
    );
}
