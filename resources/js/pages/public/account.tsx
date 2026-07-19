import { Form, Head } from '@inertiajs/react';
import { KeyRound, UserX } from 'lucide-react';
import {
    destroy,
    updatePassword,
} from '@/actions/App/Http/Public/Controllers/ReaderAccountController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ReaderAccount({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Account" />
            <div className="mx-auto grid w-full max-w-2xl gap-6 px-4 py-12">
                <header>
                    <h1 className="font-display text-3xl font-semibold tracking-tight">
                        Your account
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Manage the password for your reader account, or delete
                        it entirely.
                    </p>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="size-5" aria-hidden />
                            Change password
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...updatePassword.form()}
                            resetOnSuccess
                            className="grid gap-4"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="current-password">
                                            Current password
                                        </Label>
                                        <Input
                                            id="current-password"
                                            name="current_password"
                                            type="password"
                                            autoComplete="current-password"
                                            required
                                        />
                                        <InputError
                                            message={errors.current_password}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="new-password">
                                            New password
                                        </Label>
                                        <Input
                                            id="new-password"
                                            name="password"
                                            type="password"
                                            autoComplete="new-password"
                                            passwordrules={passwordRules}
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirm-password">
                                            Confirm new password
                                        </Label>
                                        <Input
                                            id="confirm-password"
                                            name="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            required
                                        />
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Update password
                                        </Button>
                                        {recentlySuccessful && (
                                            <p className="text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                                Password updated.
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card className="border-destructive/40">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <UserX className="size-5" aria-hidden />
                            Delete account
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <p className="text-sm text-muted-foreground">
                            Deleting your account removes your reader profile
                            and all of your comments permanently. This cannot be
                            undone.
                        </p>
                        <Form {...destroy.form()} className="grid gap-4">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="delete-password">
                                            Confirm with your password
                                        </Label>
                                        <Input
                                            id="delete-password"
                                            name="password"
                                            type="password"
                                            autoComplete="current-password"
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        className="w-fit"
                                        disabled={processing}
                                    >
                                        Delete my account
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
