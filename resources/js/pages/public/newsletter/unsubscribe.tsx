import { Form, Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Public/Controllers/NewsletterUnsubscribeController';
import { Button } from '@/components/ui/button';

export default function Unsubscribe({ token }: { token: string }) {
    return (
        <>
            <Head title="Unsubscribe" />
            <div className="mx-auto grid max-w-xl gap-5 px-4 py-20 text-center">
                <h1 className="font-display text-3xl font-semibold">
                    Unsubscribe from email
                </h1>
                <p className="text-muted-foreground">
                    Your encrypted email address will be erased immediately.
                </p>
                <Form {...store.form(token)}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={processing}
                        >
                            Unsubscribe
                        </Button>
                    )}
                </Form>
            </div>
        </>
    );
}
