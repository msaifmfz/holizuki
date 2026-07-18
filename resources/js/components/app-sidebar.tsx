import { Link } from '@inertiajs/react';
import { FileText, FolderOpen, Inbox, LayoutGrid, Tags } from 'lucide-react';
import { index as categoriesIndex } from '@/actions/App/Http/Controllers/CategoryController';
import { index as inboxIndex } from '@/actions/App/Http/Controllers/ContactSubmissionController';
import { index as postsIndex } from '@/actions/App/Http/Controllers/PostController';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/TagController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Posts',
        href: postsIndex(),
        icon: FileText,
    },
    {
        title: 'Categories',
        href: categoriesIndex(),
        icon: FolderOpen,
    },
    {
        title: 'Tags',
        href: tagsIndex(),
        icon: Tags,
    },
    {
        title: 'Inbox',
        href: inboxIndex(),
        icon: Inbox,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
