import { Link } from '@inertiajs/react';
import {
    Award,
    BarChart3,
    FileText,
    FolderOpen,
    Inbox,
    LayoutGrid,
    MessageSquare,
    Settings2,
    Tags,
    UsersRound,
} from 'lucide-react';
import {
    achievements,
    audience,
    posts as analyticsPosts,
} from '@/actions/App/Http/Admin/Controllers/AnalyticsDashboardController';
import { edit as analyticsSettings } from '@/actions/App/Http/Admin/Controllers/AnalyticsSettingsController';
import { index as categoriesIndex } from '@/actions/App/Http/Admin/Controllers/CategoryController';
import { index as commentsIndex } from '@/actions/App/Http/Admin/Controllers/CommunityCommentController';
import { index as subscribersIndex } from '@/actions/App/Http/Admin/Controllers/CommunitySubscriberController';
import { index as inboxIndex } from '@/actions/App/Http/Admin/Controllers/ContactSubmissionController';
import { index as postsIndex } from '@/actions/App/Http/Admin/Controllers/PostController';
import { index as tagsIndex } from '@/actions/App/Http/Admin/Controllers/TagController';
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
        title: 'Post analytics',
        href: analyticsPosts(),
        icon: BarChart3,
    },
    {
        title: 'Audience',
        href: audience(),
        icon: UsersRound,
    },
    {
        title: 'Achievements',
        href: achievements(),
        icon: Award,
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
    {
        title: 'Comments',
        href: commentsIndex(),
        icon: MessageSquare,
    },
    {
        title: 'Subscribers',
        href: subscribersIndex(),
        icon: UsersRound,
    },
    {
        title: 'Analytics settings',
        href: analyticsSettings(),
        icon: Settings2,
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
