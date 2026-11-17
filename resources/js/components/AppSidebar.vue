<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BarChart3, ImagePlus } from '@lucide/vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as generationsIndex } from '@/routes/generations';
import type { NavItem } from '@/types';

const page = usePage<{ auth: { user: { role: string } } }>();
const mainNavItems: NavItem[] = [
    {
        title: 'Generations',
        href: generationsIndex(),
        icon: ImagePlus,
    },
];
if (page.props.auth.user.role === 'admin') {
    mainNavItems.push({
        title: 'Cost report',
        href: '/admin/reports/costs',
        icon: BarChart3,
    });
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="generationsIndex()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
