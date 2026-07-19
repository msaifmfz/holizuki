import { Form, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { store } from '@/actions/App/Http/Public/Controllers/NewsletterSubscriptionController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    location: 'article_end' | 'footer';
    postId?: number;
    className?: string;
};

export default function NewsletterForm({ location, postId, className }: Props) {
    const { community } = usePage().props;

    return (
        <section
            className={cn(
                'grid gap-4 rounded-xl border bg-muted/30 p-5',
                className,
            )}
            aria-labelledby={`newsletter-${location}`}
        >
            <div className="grid gap-1">
                <h2
                    id={`newsletter-${location}`}
                    className="flex items-center gap-2 font-display text-lg font-semibold"
                >
                    <Mail className="size-4" aria-hidden />
                    New writing, occasionally
                </h2>
                <p className="text-sm leading-6 text-muted-foreground">
                    Get new articles by email. Confirm with one click and
                    unsubscribe whenever you like.
                </p>
            </div>

            <Form {...store.form()} resetOnSuccess>
                {({ processing, errors, wasSuccessful }) => (
                    <div className="grid gap-3">
                        {postId && (
                            <input
                                type="hidden"
                                name="source_post_id"
                                value={postId}
                            />
                        )}
                        <input
                            type="hidden"
                            name="source_location"
                            value={location}
                        />
                        <input
                            type="hidden"
                            name="consent_version"
                            value={community.consentVersion}
                        />
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <div className="min-w-0 flex-1">
                                <Label
                                    htmlFor={`newsletter-email-${location}`}
                                    className="sr-only"
                                >
                                    Email address
                                </Label>
                                <Input
                                    id={`newsletter-email-${location}`}
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    placeholder="you@example.com"
                                />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Subscribe
                            </Button>
                        </div>
                        <InputError message={errors.email} />
                        {wasSuccessful && (
                            <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                Check your inbox for the confirmation link.
                            </p>
                        )}
                    </div>
                )}
            </Form>
        </section>
    );
}
