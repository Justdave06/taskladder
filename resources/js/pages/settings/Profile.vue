<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import {
    LogOut, Settings, CheckCircle, Clock, FolderKanban,
    Activity, User as UserIcon, Mail, X,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ProjectMember {
    id: number; user_id: number; role_id: number | null; role: string | null;
}

interface ProjectData {
    id: number; title: string; created_by: number; members: ProjectMember[];
}

interface TaskUser {
    id: number; name: string;
}

interface RecentTask {
    id: number; title: string; status: string; priority: string;
    created_at: string; project_member: { user: TaskUser } | null;
}

const page = usePage();
const authUser = page.props.auth.user as { id: number; name: string; email: string; is_superadmin: boolean; is_company_admin: boolean };

const displayRole = computed(() => {
    if (authUser.is_superadmin) return 'Superadmin';
    if (authUser.is_company_admin) return 'Company Owner';
    if (isBoss.value) return 'Admin';
    return currentMember.value?.role || 'Member';
});

const props = defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
    projects: ProjectData[];
    taskStats: { total_assigned: number; completed: number; pending: number };
    recentTasks: RecentTask[];
}>();

const activeTab = ref<'about' | 'projects' | 'activity'>('about');
const editMode = ref(false);

const isBoss = computed(() =>
    props.projects.some(p => p.created_by === authUser.id),
);

const currentMember = computed(() => {
    for (const p of props.projects) {
        const m = p.members.find(m => m.user_id === authUser.id);

        if (m) {
return m;
}
    }

    return null;
});

function logout() {
    router.post('/logout');
}

function timeAgo(date: string): string {
    const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000);

    if (seconds < 60) {
return 'just now';
}

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
return `${minutes}m ago`;
}

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
return `${hours}h ago`;
}

    const days = Math.floor(hours / 24);

    return `${days}d ago`;
}

const progressPct = computed(() => {
    if (props.taskStats.total_assigned === 0) {
return 0;
}

    return Math.round((props.taskStats.completed / props.taskStats.total_assigned) * 100);
});

const priorityDots: Record<string, string> = {
    high: 'bg-red-500', med: 'bg-amber-500', low: 'bg-yellow-500', unimportant: 'bg-gray-400',
};
const priorityLabels: Record<string, string> = {
    high: 'High', med: 'Medium', low: 'Low', unimportant: 'Unimportant',
};
</script>

<template>
    <Head title="Profile" />

    <div class="mx-auto max-w-3xl py-4 px-4 lg:px-0">
        <!-- Cover + Avatar -->
        <div class="overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
            <div class="h-40 bg-gradient-to-r from-[#2563EB] to-[#7C3AED] lg:h-52" />
            <div class="relative px-4 pb-4">
                <div class="flex flex-col items-center -mt-10 sm:flex-row sm:items-end sm:gap-4">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-white bg-[#EEF3FF] text-2xl font-bold text-[#2563EB] shadow-lg">
                        {{ authUser.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="mt-2 text-center sm:mt-0 sm:text-left">
                        <h1 class="text-xl font-bold text-[#0F1623]">{{ authUser.name }}</h1>
                        <div class="flex items-center gap-2 text-sm text-[#5A6278]">
                            <Mail class="h-3.5 w-3.5" /> {{ authUser.email }}
                        </div>
                        <div class="mt-0.5 text-xs text-[#2563EB] font-medium">
                            {{ displayRole }}
                        </div>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="mt-4 flex flex-wrap gap-4 border-t border-[#E4E7F0] pt-4">
                    <div class="flex items-center gap-2 text-xs text-[#5A6278]">
                        <FolderKanban class="h-4 w-4 text-[#2563EB]" />
                        <span><strong class="text-[#0F1623]">{{ projects.length }}</strong> Projects</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[#5A6278]">
                        <CheckCircle class="h-4 w-4 text-green-500" />
                        <span><strong class="text-[#0F1623]">{{ taskStats.completed }}</strong> Completed</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[#5A6278]">
                        <Clock class="h-4 w-4 text-amber-500" />
                        <span><strong class="text-[#0F1623]">{{ taskStats.pending }}</strong> Pending</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[#5A6278]">
                        <Activity class="h-4 w-4 text-blue-500" />
                        <span><strong class="text-[#0F1623]">{{ progressPct }}%</strong> Done</span>
                    </div>
                </div>

                <!-- Progress bar -->
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-[#E4E7F0]">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#2563EB] to-green-500 transition-all" :style="{ width: progressPct + '%' }" />
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-4 flex gap-1 rounded-xl border border-[#E4E7F0] bg-white p-1">
            <button
                class="flex-1 rounded-lg px-3 py-2 text-xs font-medium transition-all"
                :class="activeTab === 'about' ? 'bg-[#0F1623] text-white' : 'text-[#5A6278] hover:bg-[#F0F2F8]'"
                @click="activeTab = 'about'"
            >About</button>
            <button
                class="flex-1 rounded-lg px-3 py-2 text-xs font-medium transition-all"
                :class="activeTab === 'projects' ? 'bg-[#0F1623] text-white' : 'text-[#5A6278] hover:bg-[#F0F2F8]'"
                @click="activeTab = 'projects'"
            >Projects</button>
            <button
                class="flex-1 rounded-lg px-3 py-2 text-xs font-medium transition-all"
                :class="activeTab === 'activity' ? 'bg-[#0F1623] text-white' : 'text-[#5A6278] hover:bg-[#F0F2F8]'"
                @click="activeTab = 'activity'"
            >Activity</button>
        </div>

        <!-- About Tab -->
        <div v-if="activeTab === 'about'" class="mt-4 space-y-4">
            <div class="rounded-2xl border border-[#E4E7F0] bg-white p-4">
                <h2 class="mb-3 text-sm font-bold text-[#0F1623]">About</h2>
                <div class="space-y-3 text-xs text-[#5A6278]">
                    <div class="flex items-center gap-3">
                        <UserIcon class="h-4 w-4 text-[#2563EB]" />
                        <span><strong class="text-[#0F1623]">Role:</strong> {{ displayRole }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <Mail class="h-4 w-4 text-[#2563EB]" />
                        <span><strong class="text-[#0F1623]">Email:</strong> {{ authUser.email }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <FolderKanban class="h-4 w-4 text-[#2563EB]" />
                        <span><strong class="text-[#0F1623]">Projects:</strong> {{ projects.length }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <CheckCircle class="h-4 w-4 text-green-500" />
                        <span><strong class="text-[#0F1623]">Tasks completed:</strong> {{ taskStats.completed }} / {{ taskStats.total_assigned }}</span>
                    </div>
                </div>
            </div>

            <!-- Edit Profile form -->
            <div v-if="editMode" class="rounded-2xl border border-[#E4E7F0] bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[#0F1623]">Edit Profile</h2>
                    <button class="flex h-6 w-6 items-center justify-center rounded hover:bg-[#F0F2F8] text-[#9BA3B8]" @click="editMode = false">
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="authUser.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="authUser.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>
                    <div class="flex items-center justify-between">
                        <Button type="button" variant="outline" size="sm" @click="editMode = false">Cancel</Button>
                        <Button size="sm" :disabled="processing">Save</Button>
                    </div>
                </Form>
            </div>

            <!-- Settings card -->
            <div v-if="!editMode" class="rounded-2xl border border-[#E4E7F0] bg-white p-4">
                <h2 class="mb-3 text-sm font-bold text-[#0F1623]">Settings</h2>
                <div class="space-y-2">
                    <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-xs text-[#5A6278] hover:bg-[#F0F2F8] transition" @click="editMode = true">
                        <Settings class="h-4 w-4" />
                        Edit Profile
                    </button>
                    <button class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-xs text-red-600 hover:bg-red-50 transition" @click="logout">
                        <LogOut class="h-4 w-4" />
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Projects Tab -->
        <div v-if="activeTab === 'projects'" class="mt-4 space-y-3">
            <div v-if="projects.length === 0" class="rounded-2xl border border-dashed border-[#E4E7F0] px-6 py-12 text-center text-sm text-[#9BA3B8]">
                No projects yet.
            </div>
            <div
                v-for="p in projects"
                :key="p.id"
                class="rounded-2xl border border-[#E4E7F0] bg-white p-4 flex items-center gap-3"
            >
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#EEF3FF] text-sm font-bold text-[#2563EB]">
                    {{ p.title.charAt(0).toUpperCase() }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-[#0F1623]">{{ p.title }}</div>
                    <div class="text-[11px] text-[#5A6278]">
                        {{ p.created_by === authUser.id ? 'Admin' : (p.members[0]?.role || 'Member') }}
                    </div>
                </div>
                <span class="rounded-full bg-[#EEF3FF] px-2.5 py-0.5 text-[10px] font-medium text-[#2563EB]">
                    {{ p.created_by === authUser.id ? 'Owner' : 'Member' }}
                </span>
            </div>
        </div>

        <!-- Activity Tab -->
        <div v-if="activeTab === 'activity'" class="mt-4 space-y-3">
            <div v-if="recentTasks.length === 0" class="rounded-2xl border border-dashed border-[#E4E7F0] px-6 py-12 text-center text-sm text-[#9BA3B8]">
                No recent activity.
            </div>
            <div
                v-for="task in recentTasks"
                :key="task.id"
                class="rounded-2xl border border-[#E4E7F0] bg-white p-4"
            >
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-[#0F1623]">{{ task.title }}</div>
                        <div class="mt-0.5 flex items-center gap-2 text-[11px] text-[#9BA3B8]">
                            <span class="inline-block h-2 w-2 rounded-full" :class="priorityDots[task.priority] || 'bg-gray-400'" />
                            {{ priorityLabels[task.priority] || task.priority }}
                            <span>·</span>
                            <span :class="task.status === 'completed' ? 'text-green-500' : 'text-amber-500'">
                                {{ task.status === 'completed' ? 'Done' : 'In progress' }}
                            </span>
                        </div>
                    </div>
                    <span class="text-[10px] text-[#9BA3B8] shrink-0">{{ timeAgo(task.created_at) }}</span>
                </div>
            </div>
        </div>
    </div>

    <DeleteUser />
</template>
