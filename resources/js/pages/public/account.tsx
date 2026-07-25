import { Form, Head } from '@inertiajs/react';

import {
    destroy,
    updatePassword,
} from '@/actions/App/Http/Public/Controllers/ReaderAccountController';
import InputError from '@/components/input-error';
import PageHeader from '@/components/public/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * A reader's own account settings.
 *
 * Was the one public page built out of shadcn `Card`s, which made it read as a
 * slice of the admin console that had wandered onto the blog. It is now ruled
 * sections on the reader surface like every other page: an eyebrow, a heading,
 * and hairline-separated groups. The destructive section is marked by its rule
 * and its heading rather than by a tinted border around a box.
 */
export default function ReaderAccount({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Account" />
            <div className="mx-auto grid w-full max-w-2xl gap-12 px-4 py-12">
                <PageHeader
                    eyebrow="Account"
                    title="Your account"
                    description="Manage the password for your reader account, or delete it entirely."
                />

                <section
                    className="grid gap-5 border-t border-border pt-6"
                    aria-labelledby="change-password"
                >
                    <h2 id="change-password" className="display-md">
                        Change password
                    </h2>

                    <Form
                        {...updatePassword.form()}
                        resetOnSuccess
                        className="grid gap-5"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <Field
                                    id="current-password"
                                    name="current_password"
                                    label="Current password"
                                    autoComplete="current-password"
                                    error={errors.current_password}
                                />
                                <Field
                                    id="new-password"
                                    name="password"
                                    label="New password"
                                    autoComplete="new-password"
                                    passwordRules={passwordRules}
                                    error={errors.password}
                                />
                                <Field
                                    id="confirm-password"
                                    name="password_confirmation"
                                    label="Confirm new password"
                                    autoComplete="new-password"
                                />
                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="micro-cta h-10 rounded-none px-5"
                                    >
                                        Update password
                                    </Button>
                                    {recentlySuccessful && (
                                        <p className="text-sm font-medium text-success">
                                            Password updated.
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section
                    className="grid gap-5 border-t border-border pt-6"
                    aria-labelledby="delete-account"
                >
                    <h2 id="delete-account" className="display-md">
                        Delete account
                    </h2>
                    <p className="max-w-xl font-body leading-relaxed text-muted-foreground">
                        Deleting your account removes your reader profile and
                        all of your comments permanently. This cannot be undone.
                    </p>

                    <Form {...destroy.form()} className="grid gap-5">
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    id="delete-password"
                                    name="password"
                                    label="Confirm with your password"
                                    autoComplete="current-password"
                                    error={errors.password}
                                />
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    className="micro-cta h-10 w-fit rounded-none px-5"
                                >
                                    Delete my account
                                </Button>
                            </>
                        )}
                    </Form>
                </section>
            </div>
        </>
    );
}

/**
 * The three password inputs were the same eleven lines each. The label sits in
 * the tracked-caps meta style so a form on the reader surface looks like it
 * belongs to the blog rather than to a settings panel.
 */
function Field({
    id,
    name,
    label,
    autoComplete,
    passwordRules,
    error,
}: {
    id: string;
    name: string;
    label: string;
    autoComplete: 'current-password' | 'new-password';
    passwordRules?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="eyebrow text-muted-foreground">
                {label}
            </Label>
            <Input
                id={id}
                name={name}
                type="password"
                autoComplete={autoComplete}
                passwordrules={passwordRules}
                required
                className="h-11 rounded-none border-border font-body shadow-none"
            />
            <InputError message={error} />
        </div>
    );
}
