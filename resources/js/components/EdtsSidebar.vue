<script setup lang="ts">
import {
    Home,
    FilePlus,
    Search,
    FileText,
    LayoutDashboard,
    Inbox,
    Archive,
    Building2,
    BookType,
    Shield,
    ChevronLeft,
    ChevronRight,
    type Component,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface SidebarItem {
    id: string;
    label: string;
    icon: Component;
}

const mainItems: SidebarItem[] = [
    { id: 'home', label: 'Home', icon: Home },
    { id: 'request', label: 'Request', icon: FilePlus },
    { id: 'track', label: 'Track', icon: Search },
    { id: 'my-requests', label: 'My Requests', icon: FileText },
];

const adminItems: SidebarItem[] = [
    { id: 'admin-dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { id: 'admin-queue', label: 'Queue', icon: Inbox },
    { id: 'admin-records', label: 'Records', icon: Archive },
];

const manageItems: SidebarItem[] = [
    { id: 'manage-depts', label: 'Departments', icon: Building2 },
    { id: 'manage-doctypes', label: 'Doc Types', icon: BookType },
    { id: 'manage-admins', label: 'Admins', icon: Shield },
];

const props = defineProps<{
    modelValue: string;
    isAdmin: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const collapsed = defineModel<boolean>('collapsed', { default: false });

function select(id: string) {
    emit('update:modelValue', id);
}

function toggleCollapse() {
    collapsed.value = !collapsed.value;
}

const sidebarWidth = computed(() => collapsed.value ? 'w-14' : 'w-48');
</script>

<template>
    <aside
        :class="[sidebarWidth, 'relative flex shrink-0 flex-col gap-1 rounded-xl border border-gray-200/60 bg-white p-1.5 shadow-sm transition-all duration-300 dark:border-gray-700/40 dark:bg-gray-800/60']"
    >
        <button
            class="flex items-center justify-center rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300"
            @click="toggleCollapse"
            :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
            <ChevronLeft v-if="!collapsed" class="h-4 w-4" />
            <ChevronRight v-else class="h-4 w-4" />
        </button>

        <div class="flex flex-col gap-0.5">
            <button
                v-for="item in mainItems"
                :key="item.id"
                class="flex items-center gap-2.5 rounded-lg px-2 py-2 text-xs font-medium transition"
                :class="modelValue === item.id
                    ? 'bg-[#185FA5] text-white'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'"
                @click="select(item.id)"
                :title="collapsed ? item.label : ''"
            >
                <component :is="item.icon" class="h-4.5 w-4.5 shrink-0" />
                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
            </button>
        </div>

        <template v-if="isAdmin">
            <div v-if="!collapsed" class="px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                Admin
            </div>
            <div v-if="collapsed" class="mx-2 h-px bg-gray-200 dark:bg-gray-700" />

            <div class="flex flex-col gap-0.5">
                <button
                    v-for="item in adminItems"
                    :key="item.id"
                    class="flex items-center gap-2.5 rounded-lg px-2 py-2 text-xs font-medium transition"
                    :class="modelValue === item.id
                        ? 'bg-[#185FA5] text-white'
                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'"
                    @click="select(item.id)"
                    :title="collapsed ? item.label : ''"
                >
                    <component :is="item.icon" class="h-4.5 w-4.5 shrink-0" />
                    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                </button>
            </div>

            <div v-if="!collapsed" class="px-2 pt-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                Manage
            </div>
            <div v-if="collapsed" class="mx-2 h-px bg-gray-200 dark:bg-gray-700" />

            <div class="flex flex-col gap-0.5">
                <button
                    v-for="item in manageItems"
                    :key="item.id"
                    class="flex items-center gap-2.5 rounded-lg px-2 py-2 text-xs font-medium transition"
                    :class="modelValue === item.id
                        ? 'bg-[#185FA5] text-white'
                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'"
                    @click="select(item.id)"
                    :title="collapsed ? item.label : ''"
                >
                    <component :is="item.icon" class="h-4.5 w-4.5 shrink-0" />
                    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
                </button>
            </div>
        </template>
    </aside>
</template>
