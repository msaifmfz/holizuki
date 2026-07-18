import { Link, usePage } from '@inertiajs/react';
import { Menu, Rss, Search } from 'lucide-react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggle from '@/components/appearance-toggle';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login } from '@/routes';
import { about, search as searchRoute, privacy, terms } from '@/routes/public';
import { show as categoryShow } from '@/routes/public/categories';
import { create as contact } from '@/routes/public/contact';

const navLinks = [
    { title: 'Home', href: home },
    { title: 'About', href: about },
    { title: 'Contact', href: contact },
];

export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, footerCategories, name } = usePage().props;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    return (
        <div className="flex min-h-screen flex-col">
            <header className="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur">
                <div className="mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-3 px-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-2"
                        aria-label={`${name} home`}
                        prefetch
                    >
                        <span className="flex aspect-square size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                        </span>
                        <span className="font-display text-lg font-semibold tracking-tight">
                            {name}
                        </span>
                    </Link>

                    <nav
                        className="hidden items-center gap-1 md:flex"
                        aria-label="Main"
                    >
                        {navLinks.map((link) => (
                            <Button
                                key={link.title}
                                asChild
                                variant="ghost"
                                size="sm"
                            >
                                <Link href={link.href()} prefetch>
                                    {link.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>

                    <div className="flex items-center gap-1">
                        <Button asChild variant="ghost" size="icon">
                            <Link
                                href={searchRoute()}
                                aria-label="Search posts"
                                prefetch
                            >
                                <Search />
                            </Link>
                        </Button>
                        <AppearanceToggle />
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="hidden sm:inline-flex"
                        >
                            <Link href={auth.user ? dashboard() : login()}>
                                {auth.user ? 'Dashboard' : 'Sign in'}
                            </Link>
                        </Button>

                        <Sheet
                            open={mobileNavOpen}
                            onOpenChange={setMobileNavOpen}
                        >
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="md:hidden"
                                    aria-label="Open menu"
                                >
                                    <Menu />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right" className="w-64">
                                <SheetHeader>
                                    <SheetTitle className="font-display">
                                        {name}
                                    </SheetTitle>
                                </SheetHeader>
                                <nav
                                    className="grid gap-1 px-4"
                                    aria-label="Mobile"
                                >
                                    {navLinks.map((link) => (
                                        <Button
                                            key={link.title}
                                            asChild
                                            variant="ghost"
                                            className="justify-start"
                                            onClick={() =>
                                                setMobileNavOpen(false)
                                            }
                                        >
                                            <Link href={link.href()}>
                                                {link.title}
                                            </Link>
                                        </Button>
                                    ))}
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="mt-2 justify-start"
                                        onClick={() => setMobileNavOpen(false)}
                                    >
                                        <Link
                                            href={
                                                auth.user
                                                    ? dashboard()
                                                    : login()
                                            }
                                        >
                                            {auth.user
                                                ? 'Dashboard'
                                                : 'Sign in'}
                                        </Link>
                                    </Button>
                                </nav>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="mt-16 border-t">
                <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12 md:grid-cols-3">
                    <div className="grid content-start gap-3">
                        <div className="flex items-center gap-2">
                            <span className="flex aspect-square size-7 items-center justify-center rounded-md bg-primary text-primary-foreground">
                                <AppLogoIcon className="size-4 fill-current text-white dark:text-black" />
                            </span>
                            <span className="font-display text-lg font-semibold">
                                {name}
                            </span>
                        </div>
                        <p className="max-w-xs text-sm text-muted-foreground">
                            Writing on software, product, and the craft of
                            building for the web.
                        </p>
                    </div>

                    {footerCategories.length > 0 && (
                        <nav
                            className="grid content-start gap-2"
                            aria-label="Topics"
                        >
                            <h2 className="text-sm font-semibold">Topics</h2>
                            {footerCategories.map((category) => (
                                <Link
                                    key={category.slug}
                                    href={categoryShow(category.slug)}
                                    className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    {category.name}
                                </Link>
                            ))}
                        </nav>
                    )}

                    <nav className="grid content-start gap-2" aria-label="Site">
                        <h2 className="text-sm font-semibold">Explore</h2>
                        <Link
                            href={about()}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            About
                        </Link>
                        <Link
                            href={contact()}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Contact
                        </Link>
                        <Link
                            href={privacy()}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Privacy Policy
                        </Link>
                        <Link
                            href={terms()}
                            className="text-sm text-muted-foreground transition-colors hover:text-foreground"
                        >
                            Terms of Use
                        </Link>
                    </nav>
                </div>
                <div className="border-t border-border/60">
                    <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-muted-foreground">
                        <p>
                            © {new Date().getFullYear()} {name}. All rights
                            reserved.
                        </p>
                        <a
                            href="/feed"
                            className="flex items-center gap-1 transition-colors hover:text-foreground"
                        >
                            <Rss className="size-3.5" />
                            RSS
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
}
