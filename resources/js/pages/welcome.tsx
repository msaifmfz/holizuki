import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';

const highlights = [
    'Create and refine drafts',
    'Preview, schedule, and publish',
];

export default function Welcome() {
    const { auth } = usePage().props;
    const destination = auth.user ? dashboard() : login();

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        <Link
                            href={destination}
                            className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                        >
                            {auth.user ? 'Dashboard' : 'Log in'}
                        </Link>
                    </nav>
                </header>

                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <div className="flex-1 rounded-b-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-r-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                            <h1 className="mb-1 font-medium">
                                Write, revise, and publish
                            </h1>
                            <p className="mb-2 text-neutral-600 dark:text-neutral-400">
                                Holizuki keeps every draft, revision, preview,
                                and scheduled post in one focused workspace.
                            </p>

                            <ul className="mb-4 flex flex-col lg:mb-6">
                                {highlights.map((highlight, index) => (
                                    <li
                                        key={highlight}
                                        className={`relative flex items-center gap-4 py-2 before:absolute before:left-[0.4rem] before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] ${
                                            index === 0
                                                ? 'before:top-1/2 before:bottom-0'
                                                : 'before:top-0 before:bottom-1/2'
                                        }`}
                                    >
                                        <span className="relative bg-white py-1 dark:bg-[#161615]">
                                            <span className="flex size-3.5 items-center justify-center rounded-full border border-[#e3e3e0] bg-[#FDFDFC] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)] dark:border-[#3E3E3A] dark:bg-[#161615]">
                                                <span className="size-1.5 rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A]" />
                                            </span>
                                        </span>
                                        <span className="font-medium">
                                            {highlight}
                                        </span>
                                    </li>
                                ))}
                            </ul>

                            <Link
                                href={destination}
                                className="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                            >
                                {auth.user
                                    ? 'Open dashboard'
                                    : 'Log in to continue'}
                            </Link>
                        </div>

                        <div className="relative -mb-px aspect-[335/364] w-full shrink-0 overflow-hidden rounded-t-lg bg-[#fff2f2] lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg dark:bg-[#1D0002]">
                            <div className="absolute -top-24 -right-20 size-64 rounded-full border border-[#f5300326] dark:border-[#f6150033]" />
                            <div className="absolute -bottom-28 -left-24 size-72 rounded-full border border-[#f5300326] dark:border-[#f6150033]" />
                            <div className="relative flex h-full flex-col items-center justify-center gap-5 text-[#F53003] transition-all duration-750 dark:text-[#F61500] starting:translate-y-6 starting:opacity-0">
                                <div className="flex size-28 items-center justify-center rounded-3xl bg-current shadow-[0_24px_70px_rgba(245,48,3,0.24)]">
                                    <AppLogoIcon className="size-16 fill-current text-white" />
                                </div>
                                <span className="text-3xl font-semibold tracking-tight">
                                    Holizuki
                                </span>
                            </div>
                            <div className="pointer-events-none absolute inset-0 rounded-t-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-t-none lg:rounded-r-lg dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]" />
                        </div>
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block" />
            </div>
        </>
    );
}
