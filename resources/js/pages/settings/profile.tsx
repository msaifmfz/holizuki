import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileAvatarController from '@/actions/App/Http/Controllers/Settings/ProfileAvatarController';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth, SocialLinks } from '@/types';

type PageProps = {
    auth: Auth;
};

const socialFields: Array<{ key: keyof SocialLinks; label: string }> = [
    { key: 'website', label: 'Website' },
    { key: 'x', label: 'X (Twitter)' },
    { key: 'github', label: 'GitHub' },
    { key: 'linkedin', label: 'LinkedIn' },
];

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const getInitials = useInitials();
    const avatarInput = useRef<HTMLInputElement>(null);

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="space-y-6 border-t pt-6">
                                <Heading
                                    variant="small"
                                    title="Author profile"
                                    description="Shown publicly next to your posts and on your author page"
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="author_slug">
                                        Author URL slug
                                    </Label>
                                    <Input
                                        id="author_slug"
                                        name="author_slug"
                                        defaultValue={
                                            auth.user.author_slug ?? ''
                                        }
                                        maxLength={100}
                                        placeholder="e.g. jane-doe"
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Your public page lives at /authors/
                                        {auth.user.author_slug ?? '…'}. Leave
                                        blank to generate it from your name.
                                    </p>
                                    <InputError message={errors.author_slug} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="bio">Short bio</Label>
                                    <Textarea
                                        id="bio"
                                        name="bio"
                                        defaultValue={auth.user.bio ?? ''}
                                        rows={3}
                                        maxLength={500}
                                        placeholder="A couple of sentences about you"
                                    />
                                    <InputError message={errors.bio} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    {socialFields.map(({ key, label }) => (
                                        <div key={key} className="grid gap-2">
                                            <Label htmlFor={`social-${key}`}>
                                                {label}
                                            </Label>
                                            <Input
                                                id={`social-${key}`}
                                                name={`social_links[${key}]`}
                                                type="url"
                                                defaultValue={
                                                    auth.user.social_links?.[
                                                        key
                                                    ] ?? ''
                                                }
                                                placeholder="https://…"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `social_links.${key}`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Avatar"
                    description="A square photo shown with your byline"
                />
                <div className="flex flex-wrap items-center gap-4">
                    <Avatar className="size-16">
                        <AvatarImage
                            src={auth.user.avatar_url ?? undefined}
                            alt={auth.user.name}
                        />
                        <AvatarFallback>
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <Form
                        {...ProfileAvatarController.store.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-2">
                                <input
                                    ref={avatarInput}
                                    type="file"
                                    name="avatar"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    onChange={(event) =>
                                        event.target.files?.length &&
                                        event.target.form?.requestSubmit()
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={processing}
                                    onClick={() => avatarInput.current?.click()}
                                >
                                    {processing
                                        ? 'Uploading…'
                                        : 'Upload avatar'}
                                </Button>
                                <InputError message={errors.avatar} />
                            </div>
                        )}
                    </Form>
                    {auth.user.avatar_url && (
                        <Form
                            {...ProfileAvatarController.destroy.form()}
                            options={{ preserveScroll: true }}
                        >
                            <Button type="submit" variant="ghost">
                                Remove
                            </Button>
                        </Form>
                    )}
                </div>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
