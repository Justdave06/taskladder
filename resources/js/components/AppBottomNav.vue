<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    ListChecks,
    Settings,
    Bell,
    User,
    MessageSquareText,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const currentUrl = computed(() => page.url);
const unreadCount = computed(() => (page.props.unreadNotificationsCount as number) || 0);

const navItems = [
    { label: 'Bulletin', href: '/bulletin', icon: MessageSquareText },
    { label: 'Desk', href: '/board', icon: LayoutDashboard },
    { label: 'Task', href: '/tasks', icon: ListChecks },
    { label: 'Messages', href: '/messages', icon: MessageSquareText },
    { label: 'Manage', href: '/manage', icon: Settings },
    { label: 'Notifi.', href: '/notifications', icon: Bell },
    { label: 'Me', href: '/settings/profile', icon: User },
];

function isActive(href: string): boolean {
    return currentUrl.value === href || currentUrl.value.startsWith(href + '/');
}
</script>

<template>
    <nav class="fixed bottom-6 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-[380px] -translate-x-1/2 items-center justify-around rounded-2xl border border-white/20 bg-white/90 px-3 py-2 shadow-xl backdrop-blur-xl dark:bg-gray-900/90">
        <Link
            v-for="item in navItems"
            :key="item.label"
            :href="item.href"
            class="relative flex flex-col items-center gap-0.5 px-2 py-1 text-[10px] transition-all duration-200"
            :class="isActive(item.href)
                ? 'font-semibold text-blue-600 dark:text-blue-400'
                : 'text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300'"
        >
            <span class="relative">
                <component :is="item.icon" class="h-5 w-5" :class="isActive(item.href) ? 'text-blue-600 dark:text-blue-400' : ''" />
                <span v-if="item.label === 'Notifi.' && unreadCount > 0" class="absolute -right-1.5 -top-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[8px] font-bold text-white">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </span>
            <span>{{ item.label }}</span>
        </Link>
    </nav>
</template>
