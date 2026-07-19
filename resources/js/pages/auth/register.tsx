import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    returnTo?: string | null;
};

export default function Register({ returnTo }: Props) {
    return (
        <>
            <Head title="Create a reader account" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        {returnTo && (
                            <input
                                type="hidden"
                                name="return_to"
                                value={returnTo}
                            />
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="name">Public display name</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                minLength={2}
                                maxLength={40}
                                autoComplete="name"
                            />
                            <p className="text-xs text-muted-foreground">
                                This name appears beside approved comments and
                                cannot be changed in this MVP.
                            </p>
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </div>

                        <div className="flex items-start gap-3 rounded-lg border p-4">
                            <Checkbox
                                id="newsletter"
                                name="newsletter"
                                value="1"
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="newsletter">
                                    Email me new writing
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Optional and unchecked by default. You will
                                    receive a separate confirmation email.
                                </p>
                            </div>
                        </div>

                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Create reader account
                        </Button>
                    </>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                Already registered?{' '}
                <TextLink href={login({ query: { return_to: returnTo } })}>
                    Log in
                </TextLink>
            </p>
        </>
    );
}

Register.layout = {
    title: 'Join the conversation',
    description: 'Create a verified reader account to comment on articles.',
};
