import { Form, Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Public/Controllers/ContactController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export default function Contact() {
    return (
        <>
            <Head title="Contact" />
            <div className="mx-auto w-full max-w-3xl px-4 py-12 sm:py-16">
                <header className="mb-10 grid gap-4">
                    <p className="flex items-center gap-2 text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        <span className="moon-dot" aria-hidden />
                        Contact
                    </p>
                    <h1 className="font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                        Say hello
                    </h1>
                    <p className="max-w-xl text-lg leading-8 text-muted-foreground">
                        Questions, corrections, or ideas for a post — drop a
                        message and it lands straight in the writing desk's
                        inbox.
                    </p>
                </header>

                <Form
                    {...store.form()}
                    resetOnSuccess
                    className="grid max-w-xl gap-5"
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="contact-name">Name</Label>
                                    <Input
                                        id="contact-name"
                                        name="name"
                                        required
                                        maxLength={100}
                                        autoComplete="name"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contact-email">Email</Label>
                                    <Input
                                        id="contact-email"
                                        name="email"
                                        type="email"
                                        required
                                        maxLength={255}
                                        autoComplete="email"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact-subject">
                                    Subject{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <Input
                                    id="contact-subject"
                                    name="subject"
                                    maxLength={150}
                                />
                                <InputError message={errors.subject} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact-message">Message</Label>
                                <Textarea
                                    id="contact-message"
                                    name="message"
                                    required
                                    rows={6}
                                    maxLength={5000}
                                />
                                <InputError message={errors.message} />
                            </div>

                            {/* Honeypot: invisible to people, tempting to bots. */}
                            <div
                                aria-hidden
                                className="absolute -left-[9999px]"
                            >
                                <label htmlFor="contact-company">Company</label>
                                <input
                                    id="contact-company"
                                    type="text"
                                    name="company"
                                    tabIndex={-1}
                                    autoComplete="off"
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Sending…' : 'Send message'}
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-muted-foreground">
                                        Sent — thanks for writing!
                                    </p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
