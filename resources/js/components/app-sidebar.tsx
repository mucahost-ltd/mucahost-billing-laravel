import { Link, usePage } from "@inertiajs/react";
import {
    BookOpen,
    Boxes,
    FolderGit2,
    LayoutGrid,
    LifeBuoy,
    ReceiptText,
    Users,
} from "lucide-react";
import AppLogo from "@/components/app-logo";
import { NavFooter } from "@/components/nav-footer";
import { NavMain } from "@/components/nav-main";
import { NavUser } from "@/components/nav-user";
import { TeamSwitcher } from "@/components/team-switcher";
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar";
import { dashboard } from "@/routes";
import { index as billing } from "@/routes/billing";
import { index as clients } from "@/routes/clients";
import { index as products } from "@/routes/products";
import { index as support } from "@/routes/support";
import type { NavItem } from "@/types";

export function AppSidebar() {
    const page = usePage();
    const dashboardUrl = page.props.currentTeam ? dashboard(page.props.currentTeam.slug) : "/";

    const mainNavItems: NavItem[] = [
        {
            title: "Dashboard",
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    if (page.props.auth.user?.role === "admin") {
        mainNavItems.push({ title: "Clients", href: clients(), icon: Users });
        mainNavItems.push({ title: "Products & services", href: products(), icon: Boxes });
        mainNavItems.push({
            title: "Billing",
            href: billing(),
            icon: ReceiptText,
        });
        mainNavItems.push({
            title: "Support",
            href: support(),
            icon: LifeBuoy,
        });
    }

    const footerNavItems: NavItem[] = [
        {
            title: "Repository",
            href: "https://github.com/laravel/react-starter-kit",
            icon: FolderGit2,
        },
        {
            title: "Documentation",
            href: "https://laravel.com/docs/starter-kits#react",
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
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
