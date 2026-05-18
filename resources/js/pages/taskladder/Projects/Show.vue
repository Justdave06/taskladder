<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    ChevronRight,
    Plus,
    Trash2,
    UserPlus,
    Check,
    X,
    Users,
    ListChecks,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
import members from '@/routes/projects/members';
import memberTasks from '@/routes/projects/members/tasks';
import { update as updateTask, destroy as destroyTask } from '@/routes/tasks';

interface TaskItem {
    id: number;
    title: string;
    is_completed: boolean;
    project_member_id: number;
}

interface Member {
    id: number;
    project_id: number;
    user_id: number;
    status: string;
    accepted_at: string | null;
    user: { id: number; name: string; email: string };
    task_items: TaskItem[];
}

interface Project {
    id: number;
    title: string;
    description: string | null;
    status: string;
    created_by: number;
    creator: { id: number; name: string };
    members: Member[];
    created_at: string;
}

interface AppUser {
    id: number;
    name: string;
    email: string;
}

const pageProps = defineProps<{
    project: Project;
    users: AppUser[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Projects',
                href: projects.index(),
            },
        ],
    },
});

const page = usePage();
const currentUser = page.props.auth.user as unknown as { id: number; name: string; email: string; is_superadmin: boolean };

const isBoss = computed(() => currentUser.is_superadmin || currentUser.id === pageProps.project.created_by);

const showInviteDialog = ref(false);
const selectedUserId = ref<string>('');
const newTaskTitles = ref<Record<number, string>>({});

const statusVariants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    draft: 'secondary',
    active: 'default',
    completed: 'outline',
    pending: 'secondary',
    accepted: 'default',
    declined: 'destructive',
};

function inviteMember(): void {
    if (!selectedUserId.value) {
 return; 
}

    router.post(members.store.url({ project: pageProps.project.id }), {
        user_id: Number(selectedUserId.value),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showInviteDialog.value = false;
            selectedUserId.value = '';
        },
    });
}

function updateMemberStatus(memberId: number, status: string): void {
    router.patch(members.update.url({ member: memberId }), { status }, {
        preserveScroll: true,
    });
}

function removeMember(memberId: number): void {
    router.delete(members.destroy.url({ member: memberId }), {
        preserveScroll: true,
    });
}

function toggleTask(task: TaskItem): void {
    router.patch(updateTask.url({ task: task.id }), {
        is_completed: !task.is_completed,
    }, {
        preserveScroll: true,
    });
}

function addTask(member: Member): void {
    const title = newTaskTitles.value[member.id]?.trim();

    if (!title) {
 return; 
}

    router.post(memberTasks.store.url({ member: member.id }), { title }, {
        preserveScroll: true,
        onSuccess: () => {
            newTaskTitles.value[member.id] = '';
        },
    });
}

function deleteTask(taskId: number): void {
    router.delete(destroyTask.url({ task: taskId }), {
        preserveScroll: true,
    });
}

function getProgress(member: Member): number {
    const total = member.task_items?.length ?? 0;

    if (total === 0) {
 return 0; 
}

    const completed = member.task_items.filter((t) => t.is_completed).length;

    return Math.round((completed / total) * 100);
}

function canToggleTask(task: TaskItem): boolean {
    const member = pageProps.project.members.find((m) => m.id === task.project_member_id);

    if (!member) {
 return false; 
}

    return currentUser.id === member.user_id || isBoss.value;
}

function availableUsers(): AppUser[] {
    const existingIds = pageProps.project.members.map((m) => m.user_id);

    return pageProps.users.filter((u) => !existingIds.includes(u.id));
}
</script>

<template>
    <Head :title="project.title" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center gap-4">
            <Button variant="outline" size="sm" as-child>
                <Link :href="projects.index.url()">
                    <ArrowLeft class="mr-1 h-4 w-4" />
                    Back
                </Link>
            </Button>
        </div>

        <div class="flex items-start justify-between">
            <div>
                <Heading :title="project.title" :description="'Created by ' + project.creator.name" />
            </div>
            <Badge :variant="statusVariants[project.status] ?? 'secondary'" class="mt-2">
                {{ project.status }}
            </Badge>
        </div>

        <p v-if="project.description" class="-mt-4 text-muted-foreground">
            {{ project.description }}
        </p>

        <Separator />

        <div class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-lg font-semibold">
                <Users class="h-5 w-5" />
                Members ({{ project.members.length }})
            </h3>
            <Button v-if="isBoss" size="sm" @click="showInviteDialog = true">
                <UserPlus class="mr-1 h-4 w-4" />
                Invite
            </Button>
        </div>

        <div v-if="project.members.length === 0" class="py-8 text-center text-muted-foreground">
            No members yet. Invite users to join this project.
        </div>

        <div v-else class="flex flex-col gap-4">
            <Card v-for="member in project.members" :key="member.id">
                <Collapsible>
                    <div class="flex items-center gap-2">
                        <CardHeader class="flex-1">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <CollapsibleTrigger as-child>
                                        <Button variant="ghost" size="icon" class="h-6 w-6">
                                            <ChevronRight class="collapsible-closed:block hidden h-4 w-4" />
                                            <ChevronDown class="collapsible-closed:hidden block h-4 w-4" />
                                        </Button>
                                    </CollapsibleTrigger>
                                    <div>
                                        <CardTitle class="text-base">{{ member.user.name }}</CardTitle>
                                        <CardDescription>{{ member.user.email }}</CardDescription>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge :variant="statusVariants[member.status] ?? 'secondary'">
                                        {{ member.status }}
                                    </Badge>
                                    <div class="flex items-center gap-1 text-sm text-muted-foreground">
                                        <ListChecks class="h-3 w-3" />
                                        {{ member.task_items?.filter((t) => t.is_completed).length ?? 0 }}/{{ member.task_items?.length ?? 0 }}
                                    </div>
                                    <Button
                                        v-if="isBoss"
                                        variant="ghost"
                                        size="icon"
                                        class="h-6 w-6 text-destructive"
                                        @click="removeMember(member.id)"
                                    >
                                        <X class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <div v-if="member.status === 'accepted' || member.status === 'completed'" class="mt-2">
                                <div class="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{ width: getProgress(member) + '%' }"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">{{ getProgress(member) }}% complete</p>
                            </div>
                            <div v-if="member.status === 'pending' && currentUser.id === member.user_id" class="mt-2 flex gap-2">
                                <Button size="sm" variant="default" @click="updateMemberStatus(member.id, 'accepted')">
                                    <Check class="mr-1 h-3 w-3" />
                                    Accept
                                </Button>
                                <Button size="sm" variant="outline" @click="updateMemberStatus(member.id, 'declined')">
                                    <X class="mr-1 h-3 w-3" />
                                    Decline
                                </Button>
                            </div>
                        </CardHeader>
                    </div>

                    <CollapsibleContent>
                        <CardContent>
                            <div class="flex flex-col gap-2">
                                <div
                                    v-for="task in member.task_items"
                                    :key="task.id"
                                    class="flex items-center gap-3 rounded-md border px-3 py-2"
                                >
                                    <Checkbox
                                        :checked="task.is_completed"
                                        :disabled="!canToggleTask(task)"
                                        @update:checked="toggleTask(task)"
                                    />
                                    <span
                                        class="flex-1 text-sm"
                                        :class="{ 'text-muted-foreground line-through': task.is_completed }"
                                    >
                                        {{ task.title }}
                                    </span>
                                    <Button
                                        v-if="isBoss"
                                        variant="ghost"
                                        size="icon"
                                        class="h-6 w-6 text-destructive"
                                        @click="deleteTask(task.id)"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                                <div v-if="member.task_items?.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                                    No tasks yet
                                </div>
                                <form
                                    v-if="isBoss"
                                    class="flex items-center gap-2"
                                    @submit.prevent="addTask(member)"
                                >
                                    <Input
                                        v-model="newTaskTitles[member.id]"
                                        placeholder="Add a task..."
                                        class="flex-1"
                                    />
                                    <Button type="submit" size="sm" :disabled="!newTaskTitles[member.id]?.trim()">
                                        <Plus class="h-3 w-3" />
                                    </Button>
                                </form>
                            </div>
                        </CardContent>
                    </CollapsibleContent>
                </Collapsible>
            </Card>
        </div>

        <Dialog v-model:open="showInviteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite Member</DialogTitle>
                    <DialogDescription>Select a user to invite to this project.</DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="user">User</Label>
                        <Select v-model="selectedUserId">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a user..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="user in availableUsers()"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ user.name }} ({{ user.email }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button :disabled="!selectedUserId" @click="inviteMember">
                        Send Invitation
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
