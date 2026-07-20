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
        title: 'Page not found',
        description:
            'The page you’re looking for doesn’t exist or may have moved.',
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
            <div className="relative isolate mx-auto grid w-full max-w-3xl place-items-center overflow-visible px-4 py-24 text-center sm:py-32">
                <div
                    aria-hidden
                    className="moon-glow absolute top-4 left-1/2 -z-10 size-80 -translate-x-1/2 rounded-full"
                />
                <p className="font-display text-7xl font-semibold tracking-tight sm:text-8xl">
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
