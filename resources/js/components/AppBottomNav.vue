<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    ListChecks,
    Settings,
    Bell,
    User,
    MessageSquareText,
    NotebookText,
    Building2,
    Users,
    ShieldCheck,
    UserPlus,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const unreadCount = computed(() => (page.props.unreadNotificationsCount as number) || 0);
const authUser = page.props.auth.user as { id: number; is_superadmin?: boolean; company_id?: number | null } | null;

const isSuperadmin = computed(() => authUser?.is_superadmin ?? false);
const hasCompany = computed(() => !!authUser?.company_id);
const pendingConnectCount = ref((page.props.pendingConnectRequests as number) || 0);

let pollInterval: ReturnType<typeof setInterval>;
onMounted(() => {
    if (!authUser?.company_id) return;
    pollInterval = setInterval(async () => {
        try {
            const res = await fetch('/connect/pending-count');
            const data = await res.json();
            pendingConnectCount.value = data.count || 0;
        } catch {}
    }, 5000);
});
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

const baseNavItems: NavItem[] = [
    { label: 'Bulletin', href: '/bulletin', icon: MessageSquareText },
    { label: 'Desk', href: '/board', icon: LayoutDashboard },
    { label: 'Task', href: '/tasks', icon: ListChecks },
    { label: 'Messages', href: '/messages', icon: MessageSquareText },
    { label: 'Manage', href: '/manage', icon: Settings },
    { label: 'Notifications', href: '/notifications', icon: Bell },
    { label: 'Ledger', href: '/ledger', icon: NotebookText },
    { label: 'Me', href: '/settings/profile', icon: User },
];

const regularNavItems = computed(() => {
    const items = [...baseNavItems];
    if (hasCompany.value) {
        items.splice(items.findIndex(i => i.label === 'Me') + 1, 0, { label: 'Connect', href: '/connect', icon: UserPlus });
    }
    return items;
});

const superadminNavItems: NavItem[] = [
    { label: 'Dashboard', href: '/admin', icon: LayoutDashboard, exact: true },
    { label: 'Companies', href: '/admin/companies', icon: Building2 },
    { label: 'Users', href: '/admin/users', icon: Users },
    { label: 'Roles', href: '/admin/roles', icon: ShieldCheck },
    { label: 'Messages', href: '/messages', icon: MessageSquareText },
    { label: 'Notifications', href: '/notifications', icon: Bell },
];

const navItems = computed(() => isSuperadmin.value ? superadminNavItems : regularNavItems.value);

function isActive(item: NavItem): boolean {
    if (item.exact) return currentUrl.value === item.href;
    return currentUrl.value === item.href || currentUrl.value.startsWith(item.href + '/');
}
</script>

<template>
    <nav class="fixed bottom-6 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-[560px] -translate-x-1/2 items-center justify-around rounded-2xl border border-white/20 bg-white/90 px-2 py-2 shadow-xl backdrop-blur-xl dark:bg-gray-900/90">
        <Link
            v-for="item in navItems"
            :key="item.label"
            :href="item.href"
            class="group relative flex flex-col items-center gap-0.5 px-1.5 py-1 text-[10px] transition-all duration-300"
            :class="isActive(item)
                ? 'font-semibold text-blue-600 dark:text-blue-400'
                : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'"
        >
            <span class="relative transition-transform duration-300 group-hover:-translate-y-1.5 group-hover:scale-110">
                <component :is="item.icon" class="h-5 w-5" :class="isActive(item) ? 'text-blue-600 dark:text-blue-400' : ''" />
                <span v-if="item.label === 'Notifications' && unreadCount > 0" class="absolute -right-1.5 -top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[8px] font-bold text-white">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
                <span v-if="item.label === 'Connect' && pendingConnectCount > 0" class="absolute -right-1.5 -top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-blue-500 px-1 text-[8px] font-bold text-white">
                    {{ pendingConnectCount > 99 ? '99+' : pendingConnectCount }}
                </span>
            </span>
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
