import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

export default function SubscriptionConfirmed() {
    return (
        <>
            <Head title="Subscription confirmed" />
            <div className="mx-auto grid max-w-xl gap-5 px-4 py-20 text-center">
                <h1 className="font-display text-3xl font-semibold">
                    You’re subscribed
                </h1>
                <p className="text-muted-foreground">
                    The next new article will arrive in your inbox.
                </p>
                <Button asChild>
                    <Link href={home()}>Return home</Link>
                </Button>
            </div>
        </>
    );
}
