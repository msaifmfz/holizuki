import { Link, usePage } from '@inertiajs/react';
import { Home, Menu, Rss, Search } from 'lucide-react';
import { MotionConfig } from 'motion/react';
import { useEffect, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';

import { edit as readerAccount } from '@/actions/App/Http/Public/Controllers/ReaderAccountController';
import { openPrivacyManager } from '@/analytics/consent';
import AppearanceToggle from '@/components/appearance-toggle';
import AnalyticsConsentManager from '@/components/public/analytics-consent';
import NewsletterForm from '@/components/public/newsletter-form';
import PublicAnalytics from '@/components/public/public-analytics';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useSurface } from '@/hooks/use-surface';
import { cn } from '@/lib/utils';
import { dashboard, home, login, logout } from '@/routes';
import {
    about,
    archive,
    feed,
    privacy,
    search as searchRoute,
    terms,
    topics,
} from '@/routes/public';
import { show as categoryShow } from '@/routes/public/categories';
import { create as contact } from '@/routes/public/contact';
import type { BreadcrumbItem } from '@/types';

const navLinks = [
    { title: 'Home', href: home, descendants: false },
    { title: 'Topics', href: topics, descendants: true },
    { title: 'Archive', href: archive, descendants: true },
    { title: 'About', href: about, descendants: false },
];

const footerLinks = [
    { title: 'All topics', href: topics },
    { title: 'Archive', href: archive },
    { title: 'Search', href: searchRoute },
    { title: 'About', href: about },
    { title: 'Contact', href: contact },
    { title: 'Privacy Policy', href: privacy },
    { title: 'Terms of Use', href: terms },
];

/**
 * The public shell.
 *
 * The header is two bands rather than one bar: a thin utility row that stays put,
 * and a masthead beneath it that scrolls away. tg-mari does this — nav on top,
 * then the wordmark centred in its own band — and it lets the wordmark be a
 * piece of type rather than a logo squeezed into a 64px strip. Once the masthead
 * has gone the utility row picks the wordmark up inline, so the site name is
 * never off screen.
 */
export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth, footerCategories, name } = usePage().props;
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();
    const { sentinelRef, passed: mastheadPassed } = useScrolledPastMasthead();

    useSurface('public');

    const isActive = (link: (typeof navLinks)[number]) =>
        link.title === 'Topics'
            ? ['/topics', '/categories/', '/tags/'].some((path) =>
                  path.endsWith('/')
                      ? isCurrentOrParentUrl(path)
                      : isCurrentUrl(path),
              )
            : link.descendants
              ? isCurrentOrParentUrl(link.href())
              : isCurrentUrl(link.href());

    const accountLabel =
        auth.user?.role === 'administrator'
            ? 'Dashboard'
            : auth.user
              ? 'Sign out'
              : 'Sign in';
    const accountHref =
        auth.user?.role === 'administrator'
            ? dashboard()
            : auth.user
              ? logout()
              : login();

    return (
        // `reducedMotion="user"` makes every animation on the public site
        // honour the OS setting: transforms are dropped, opacity is kept.
        <MotionConfig reducedMotion="user">
            <div className="flex min-h-screen flex-col">
                <PublicAnalytics />
                <AnalyticsConsentManager />
                <a
                    href="#main-content"
                    className="fixed top-2 left-2 z-[100] -translate-y-20 bg-background px-4 py-2 text-sm font-medium ring-1 ring-border transition-transform focus:translate-y-0"
                >
                    Skip to content
                </a>

                {/* Class name is load-bearing: print CSS hides it by name. */}
                <header className="site-header">
                    <div className="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
                        <div className="mx-auto flex h-11 w-full max-w-6xl items-center justify-between gap-3 px-4">
                            <nav
                                className="hidden items-center md:flex"
                                aria-label="Main"
                            >
                                {navLinks.map((link) => (
                                    <Link
                                        key={link.title}
                                        href={link.href()}
                                        prefetch
                                        aria-current={
                                            isActive(link) ? 'page' : undefined
                                        }
                                        className={cn(
                                            'eyebrow border-b-2 border-transparent px-3 py-3 transition-colors hover:text-foreground',
                                            isActive(link)
                                                ? 'border-primary text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {link.title}
                                    </Link>
                                ))}
                            </nav>

                            {/* Takes the wordmark's place once the masthead has
                                scrolled off. Hidden rather than unmounted so
                                the transition has something to animate. */}
                            <Link
                                href={home()}
                                prefetch
                                aria-hidden={!mastheadPassed}
                                tabIndex={-1}
                                className={cn(
                                    'absolute left-1/2 -translate-x-1/2 font-display text-sm [letter-spacing:0.2em] uppercase [font-stretch:112%] transition-opacity duration-300',
                                    mastheadPassed
                                        ? 'opacity-100'
                                        : 'pointer-events-none opacity-0',
                                )}
                            >
                                {name}
                            </Link>

                            <div className="ml-auto flex items-center gap-1">
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="icon"
                                    className="size-8"
                                >
                                    <Link
                                        href={searchRoute()}
                                        aria-label="Search posts"
                                        prefetch
                                    >
                                        <Search />
                                    </Link>
                                </Button>
                                <AppearanceToggle />
                                {auth.user?.role === 'reader' && (
                                    <Link
                                        href={readerAccount()}
                                        prefetch
                                        className="eyebrow hidden px-3 text-muted-foreground transition-colors hover:text-foreground sm:inline-flex"
                                    >
                                        Account
                                    </Link>
                                )}
                                <Link
                                    href={accountHref}
                                    method={
                                        auth.user?.role === 'reader'
                                            ? 'post'
                                            : 'get'
                                    }
                                    as={
                                        auth.user?.role === 'reader'
                                            ? 'button'
                                            : 'a'
                                    }
                                    className="eyebrow hidden px-3 text-muted-foreground transition-colors hover:text-foreground sm:inline-flex"
                                >
                                    {accountLabel}
                                </Link>

                                <Sheet
                                    open={mobileNavOpen}
                                    onOpenChange={setMobileNavOpen}
                                >
                                    <SheetTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 md:hidden"
                                            aria-label="Open menu"
                                        >
                                            <Menu />
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="right" className="w-72">
                                        <SheetHeader>
                                            <SheetTitle className="font-display [letter-spacing:0.18em] uppercase [font-stretch:112%]">
                                                {name}
                                            </SheetTitle>
                                        </SheetHeader>
                                        <nav
                                            className="grid px-4"
                                            aria-label="Mobile"
                                        >
                                            {navLinks.map((link) => (
                                                <Link
                                                    key={link.title}
                                                    href={link.href()}
                                                    aria-current={
                                                        isActive(link)
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    onClick={() =>
                                                        setMobileNavOpen(false)
                                                    }
                                                    className={cn(
                                                        'eyebrow border-b border-border py-4',
                                                        isActive(link)
                                                            ? 'text-primary'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {link.title}
                                                </Link>
                                            ))}
                                            {auth.user?.role === 'reader' && (
                                                <Link
                                                    href={readerAccount()}
                                                    onClick={() =>
                                                        setMobileNavOpen(false)
                                                    }
                                                    className="eyebrow border-b border-border py-4 text-muted-foreground"
                                                >
                                                    Account
                                                </Link>
                                            )}
                                            <Link
                                                href={accountHref}
                                                method={
                                                    auth.user?.role === 'reader'
                                                        ? 'post'
                                                        : 'get'
                                                }
                                                as={
                                                    auth.user?.role === 'reader'
                                                        ? 'button'
                                                        : 'a'
                                                }
                                                onClick={() =>
                                                    setMobileNavOpen(false)
                                                }
                                                className="eyebrow py-4 text-left text-primary"
                                            >
                                                {accountLabel}
                                            </Link>
                                        </nav>
                                    </SheetContent>
                                </Sheet>
                            </div>
                        </div>
                    </div>

                    <div className="mx-auto grid w-full max-w-6xl justify-items-center gap-2.5 px-4 py-9 sm:py-12">
                        <Link
                            href={home()}
                            aria-label={`${name} home`}
                            prefetch
                            className="text-center font-display text-[clamp(2rem,7vw,4rem)] leading-none [letter-spacing:0.06em] uppercase [font-stretch:118%]"
                        >
                            {name}
                        </Link>
                        <p className="eyebrow text-center text-brand-accent">
                            Fewer places, longer stays
                        </p>
                    </div>
                </header>

                {/* The trigger for the inline wordmark. Zero-height and inert —
                    it only exists to be observed. */}
                <div ref={sentinelRef} aria-hidden />

                <main id="main-content" tabIndex={-1} className="flex-1">
                    <PublicBreadcrumbTrail />
                    {children}
                </main>

                {/* Only a small margin: every page section already carries its
                    own bottom padding, and mt-24 on top of that left a void of
                    paper between the last row and the band. */}
                <footer className="site-footer band-night mt-6">
                    <div className="mx-auto w-full max-w-6xl px-4">
                        {/*
                         * An oversized ghost wordmark used to sit here, set in
                         * `text-night-foreground/12` as a watermark. It is gone
                         * because it could not be both things at once: axe scores
                         * it 1.37:1 against the band, and it needs 40% opacity to
                         * clear the 3:1 large-text threshold — at which point it
                         * stops being a texture and starts competing with the
                         * links beside it. Decorative text that cannot pass is
                         * just decoration to cut. The site name is already live
                         * text in the masthead and in the copyright line below.
                         */}
                        <div className="grid gap-12 py-14 md:grid-cols-2 lg:grid-cols-[1fr_1fr_1.6fr]">
                            {footerCategories.length > 0 && (
                                <nav
                                    className="grid content-start gap-3"
                                    aria-label="Topics"
                                >
                                    <h2 className="eyebrow text-night-accent">
                                        Where we&rsquo;ve been
                                    </h2>
                                    {footerCategories.map((category) => (
                                        <Link
                                            key={category.slug}
                                            href={categoryShow(category.slug)}
                                            className="font-display text-sm text-night-muted transition-colors hover:text-night-foreground"
                                        >
                                            {category.name}
                                        </Link>
                                    ))}
                                </nav>
                            )}

                            <nav
                                className="grid content-start gap-3"
                                aria-label="Site"
                            >
                                <h2 className="eyebrow text-night-accent">
                                    Explore
                                </h2>
                                {footerLinks.map((link) => (
                                    <Link
                                        key={link.title}
                                        href={link.href()}
                                        className="font-display text-sm text-night-muted transition-colors hover:text-night-foreground"
                                    >
                                        {link.title}
                                    </Link>
                                ))}
                            </nav>

                            <NewsletterForm location="footer" tone="night" />
                        </div>
                    </div>

                    <div className="border-t border-night-muted/25">
                        <div className="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-night-muted">
                            <p className="eyebrow">
                                © {new Date().getFullYear()} {name}
                            </p>
                            <a
                                href={feed.url()}
                                className="eyebrow flex items-center gap-1.5 transition-colors hover:text-night-foreground"
                            >
                                <Rss className="size-3" />
                                RSS
                            </a>
                            <button
                                type="button"
                                onClick={openPrivacyManager}
                                className="eyebrow underline-offset-4 transition-colors hover:text-night-foreground hover:underline"
                            >
                                Privacy choices
                            </button>
                        </div>
                    </div>
                </footer>
            </div>
        </MotionConfig>
    );
}

/**
 * Tracks whether the masthead has scrolled out from under the sticky utility
 * row, so the row can pick the wordmark up inline.
 *
 * An IntersectionObserver on a zero-height sentinel rather than a scroll
 * listener: nothing runs on the main thread while the reader is scrolling, and
 * there is no layout read per frame. Starts `false`, which is also what the
 * server renders — the masthead is on screen before anyone scrolls.
 */
function useScrolledPastMasthead() {
    const sentinelRef = useRef<HTMLDivElement | null>(null);
    const [passed, setPassed] = useState(false);

    useEffect(() => {
        const node = sentinelRef.current;

        if (!node) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => setPassed(!entry.isIntersecting),
            // The utility row is 44px tall; fire as the sentinel slides under it.
            { rootMargin: '-44px 0px 0px 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, []);

    return { sentinelRef, passed };
}

function PublicBreadcrumbTrail() {
    const page = usePage();
    const pathname = new URL(page.url, 'http://localhost').pathname;

    if (pathname === '/') {
        return null;
    }

    const props = page.props as Record<string, unknown>;
    const post = props.post as
        | {
              title?: string;
              category?: { name?: string; slug?: string } | null;
          }
        | undefined;
    const category = props.category as { name?: string } | undefined;
    const tag = props.tag as { name?: string } | undefined;
    const author = props.author as { name?: string } | undefined;
    const period = props.period as { label?: string } | undefined;
    const items: BreadcrumbItem[] = [{ title: 'Home', href: home() }];

    if (pathname.startsWith('/categories/')) {
        items.push({ title: 'Topics', href: topics() });
        items.push({ title: category?.name ?? 'Category', href: page.url });
    } else if (pathname.startsWith('/tags/')) {
        items.push({ title: 'Topics', href: topics() });
        items.push({ title: tag?.name ?? 'Tag', href: page.url });
    } else if (pathname.startsWith('/authors/')) {
        items.push({ title: author?.name ?? 'Author', href: page.url });
    } else if (pathname.startsWith('/posts/')) {
        if (post?.category?.name && post.category.slug) {
            items.push({ title: 'Topics', href: topics() });
            items.push({
                title: post.category.name,
                href: categoryShow(post.category.slug),
            });
        }

        items.push({ title: post?.title ?? 'Post', href: page.url });
    } else if (pathname.startsWith('/archive')) {
        items.push({
            title: 'Archive',
            href: pathname === archive.url() ? page.url : archive(),
        });

        if (pathname !== archive.url()) {
            items.push({
                title: period?.label ?? 'All posts',
                href: page.url,
            });
        }
    } else {
        const labels: Record<string, string> = {
            '/about': 'About',
            '/account': 'Account',
            '/contact': 'Contact',
            '/privacy': 'Privacy Policy',
            '/search': 'Search',
            '/terms': 'Terms of Use',
            '/topics': 'Topics',
        };

        items.push({
            title: labels[pathname] ?? 'Page',
            href: page.url,
        });
    }

    const last = items.length - 1;

    /*
     * Segmented rather than the shared `Breadcrumbs` component: cells divided by
     * hairlines, lifted from tg-mari. The shared component is still admin's, so
     * the two portals no longer have to agree on what a breadcrumb looks like.
     * The class name is load-bearing — print CSS hides it.
     */
    return (
        <nav
            aria-label="Breadcrumb"
            className={cn(
                'public-breadcrumbs border-y border-border',
                pathname === '/error' && 'hidden',
            )}
        >
            <ol className="mx-auto flex w-full max-w-6xl items-stretch overflow-x-auto px-4">
                {items.map((item, index) => (
                    <li
                        key={`${item.title}-${index}`}
                        // `shrink-0` + nowrap: on a narrow screen the strip
                        // should scroll sideways, not let a two-word category
                        // wrap and make its cell twice as tall as the others.
                        className="flex shrink-0 items-center border-r border-border whitespace-nowrap first:border-l"
                    >
                        {index === last ? (
                            <span
                                aria-current="page"
                                className="eyebrow max-w-[18rem] truncate px-3.5 py-3 text-foreground"
                            >
                                {item.title}
                            </span>
                        ) : (
                            <Link
                                href={item.href}
                                className="eyebrow flex items-center gap-1.5 px-3.5 py-3 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                {index === 0 && (
                                    <Home className="size-3" aria-hidden />
                                )}
                                {item.title}
                            </Link>
                        )}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
