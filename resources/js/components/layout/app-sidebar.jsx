import { NavFooter } from '@/components/common/nav-footer';
import { NavMain } from '@/components/common/nav-main';
import { NavUser } from '@/components/common/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { Link } from '@inertiajs/react';
import { BookOpen, LayoutGrid } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
        components: ['Dashboard'],
    },
    {
        title: 'Catatan Keuangan',
        url: '/income',
        icon: BookOpen,
        components: [
            'incomes/index', 
            'incomes/create', 
            'incomes/edit', 
            'outcomes/index', 
            'outcomes/create', 
            'outcomes/edit'
        ],
    },
];

const footerNavItems = [];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
