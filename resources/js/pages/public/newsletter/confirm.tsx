import { Form, Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Public/Controllers/NewsletterConfirmationController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

export default function ConfirmSubscription({ token }: { token: string }) {
    return (
        <>
            <Head title="Confirm subscription" />
            <div className="mx-auto grid max-w-xl gap-5 px-4 py-20 text-center">
                <h1 className="font-display text-3xl font-semibold">
                    Confirm your subscription
                </h1>
                <p className="text-muted-foreground">
                    Confirming is a deliberate POST action so automated email
                    scanners cannot subscribe you.
                </p>
                <Form {...store.form(token)}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            data-testid="confirm-subscription"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Confirm subscription
                        </Button>
                    )}
                </Form>
            </div>
        </>
    );
}
