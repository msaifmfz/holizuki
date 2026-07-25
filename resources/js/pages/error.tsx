import { Head, Link } from '@inertiajs/react';
import { FileText, MoveRight, Search } from 'lucide-react';
import { index as postsIndex } from '@/actions/App/Http/Admin/Controllers/PostController';
import { Button } from '@/components/ui/button';
import { dashboard, home } from '@/routes';
import { search } from '@/routes/public';

const content: Record<number, { title: string; description: string }> = {
    403: {
        title: 'Access denied',
        description: 'You don’t have permission to view this page.',
    },
    404: {
        title: 'Looks like a wrong turn',
        description:
            'The page you’re looking for doesn’t exist, or it moved on without leaving a forwarding address.',
    },
    500: {
        title: 'Something went wrong',
        description:
            'An unexpected error occurred on our side. Try again in a moment.',
    },
    503: {
        title: 'Down for maintenance',
        description: 'We’re doing some quick maintenance. Check back shortly.',
    },
};

type ErrorPageProps = {
    status: number;
    portal: 'admin' | 'public';
};

export default function ErrorPage({ status, portal }: ErrorPageProps) {
    const { title, description } = content[status] ?? content[500];
    const isAdminPortal = portal === 'admin';

    return (
        <>
            <Head title={title} />
            {/* Shared with the admin portal, so this stays on the shared tokens
                and the shared display face rather than reaching for anything in
                public.css. The decorative `moon-glow` halo that used to sit
                behind the status number went with that utility. */}
            <div className="mx-auto grid w-full max-w-3xl place-items-center px-4 py-24 text-center sm:py-32">
                <p className="font-display text-7xl font-semibold sm:text-8xl">
                    {status}
                </p>
                <h1 className="mt-4 font-display text-2xl font-semibold sm:text-3xl">
                    {title}
                </h1>
                <p className="mt-2 max-w-md text-muted-foreground">
                    {description}
                </p>
                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button asChild>
                        <Link href={isAdminPortal ? dashboard() : home()}>
                            {isAdminPortal
                                ? 'Back to dashboard'
                                : 'Back to the blog'}
                            <MoveRight aria-hidden />
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={isAdminPortal ? postsIndex() : search()}>
                            {isAdminPortal ? (
                                <FileText aria-hidden />
                            ) : (
                                <Search aria-hidden />
                            )}
                            {isAdminPortal ? 'Manage posts' : 'Search posts'}
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
