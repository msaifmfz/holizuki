import { Form, usePage } from '@inertiajs/react';

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
    /**
     * `night` restyles for the constant dark band. The surface tokens invert
     * with the theme and the band does not, so it cannot just inherit.
     */
    tone?: 'paper' | 'night';
    className?: string;
};

/**
 * Newsletter signup.
 *
 * Was a rounded tinted panel; now a rule and a heading, with the field and the
 * button sharing one hairline box. Budget Traveller runs its signup as a
 * full-width band with the heading set large to the left and the form to the
 * right, which is how this sits at the end of an article and in the footer.
 */
export default function NewsletterForm({
    location,
    postId,
    tone = 'paper',
    className,
}: Props) {
    const { community } = usePage().props;
    const night = tone === 'night';

    return (
        <section
            className={cn('grid gap-5', className)}
            aria-labelledby={`newsletter-${location}`}
        >
            <div className="grid gap-2.5">
                <p
                    className={cn(
                        'eyebrow',
                        night ? 'text-night-accent' : 'text-brand-accent',
                    )}
                >
                    Postcards
                </p>
                <h2 id={`newsletter-${location}`} className="display-md">
                    Now and then, a note from the road
                </h2>
                <p
                    className={cn(
                        'max-w-md font-body leading-relaxed',
                        night ? 'text-night-muted' : 'text-muted-foreground',
                    )}
                >
                    New trips, hotel finds, and the odd wrong turn. One click to
                    confirm, one to leave.
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
                        <div
                            className={cn(
                                'flex flex-col gap-2 sm:flex-row sm:gap-0',
                                // One shared hairline box on wider screens, so
                                // the field and the button read as one control.
                                'sm:border sm:p-0',
                                night
                                    ? 'sm:border-night-muted/40'
                                    : 'sm:border-border',
                            )}
                        >
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
                                    className={cn(
                                        'h-11 rounded-none px-4 font-body shadow-none sm:border-0',
                                        night &&
                                            'border-night-muted/40 text-night-foreground placeholder:text-night-muted',
                                    )}
                                />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing}
                                className={cn(
                                    'micro-cta h-11 rounded-none px-6',
                                    night &&
                                        'bg-night-accent text-night hover:bg-night-accent/90',
                                )}
                            >
                                {processing && <Spinner />}
                                Subscribe
                            </Button>
                        </div>
                        <InputError message={errors.email} />
                        {wasSuccessful && (
                            <p
                                className={cn(
                                    'text-sm font-medium',
                                    night
                                        ? 'text-night-accent'
                                        : 'text-success',
                                )}
                            >
                                Check your inbox for the confirmation link.
                            </p>
                        )}
                    </div>
                )}
            </Form>
        </section>
    );
}
