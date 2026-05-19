<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import team from '@/routes/team';

interface Member {
    id: number;
    user_id: number;
    status: string;
    role: string | null;
    user: { id: number; name: string; email: string };
}

interface ProjectData {
    id: number;
    title: string;
    description: string | null;
    status: string;
    creator: { id: number; name: string };
    members: Member[];
}

interface AppUser {
    id: number;
    name: string;
    email: string;
}

interface RoleData {
    id: number;
    name: string;
}

const props = defineProps<{
    projects: ProjectData[];
    allUsers: AppUser[];
    allRoles: RoleData[];
}>();

const showInviteDialog = ref(false);
const inviteForm = ref({
    project_id: props.projects[0]?.id ?? null,
    user_id: '',
    role: '',
});

const statusVariants: Record<string, 'default' | 'destructive' | 'outline' | 'secondary'> = {
    pending: 'secondary',
    accepted: 'default',
    declined: 'destructive',
    completed: 'outline',
};

const avatarColors = [
    'bg-purple-100 text-purple-700',
    'bg-green-100 text-green-700',
    'bg-amber-100 text-amber-700',
    'bg-pink-100 text-pink-700',
];

function openInvite() {
    inviteForm.value = { project_id: props.projects[0]?.id ?? null, user_id: '', role: '' };
    showInviteDialog.value = true;
}

function sendInvite() {
    if (!inviteForm.value.project_id || !inviteForm.value.user_id) {
return;
}

    router.post(team.invite.url(), inviteForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showInviteDialog.value = false;
        },
    });
}

function updateMemberRole(memberId: number, role: string) {
    router.patch(team.members.update.url({ member: memberId }), { role }, {
        preserveScroll: true,
    });
}

function removeMember(memberId: number) {
    router.delete(team.members.remove.url({ member: memberId }), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Team" />

    <div class="p-4">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold">Team</h2>
            <Button size="sm" @click="openInvite">
                <UserPlus class="mr-1 h-4 w-4" /> Invite
            </Button>
        </div>

        <div v-if="projects.length === 0" class="py-12 text-center text-sm text-muted-foreground">
            No projects yet.
        </div>

        <div v-else class="flex flex-col gap-6">
            <Card v-for="project in projects" :key="project.id">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-base">{{ project.title }}</CardTitle>
                            <p class="text-xs text-muted-foreground">
                                Created by {{ project.creator.name }} · {{ project.members.length }} members
                            </p>
                        </div>
                        <Badge :variant="statusVariants[project.status] ?? 'secondary'">
                            {{ project.status }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="project.members.length === 0" class="text-sm text-muted-foreground">
                        No members yet. Invite someone.
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="(member, idx) in project.members"
                            :key="member.id"
                            class="flex items-center gap-3 rounded-md border px-3 py-2"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold"
                                :class="avatarColors[idx % avatarColors.length]"
                            >
                                {{ member.user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium">{{ member.user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ member.user.email }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    class="text-muted-foreground hover:text-red-500"
                                    @click="removeMember(member.id)"
                                    title="Remove"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="showInviteDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite Member</DialogTitle>
                    <DialogDescription>Add a user to a project.</DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-3">
                    <div>
                        <Label>Project</Label>
                        <Select v-model="inviteForm.project_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select project" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in projects" :key="p.id" :value="p.id">
                                    {{ p.title }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>User</Label>
                        <Select v-model="inviteForm.user_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select user" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in allUsers" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.email }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label>Role</Label>
                        <select
                            v-model="inviteForm.role"
                            class="flex h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:border-blue-400"
                        >
                            <option value="">No role</option>
                            <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                    </div>
                    <Button :disabled="!inviteForm.project_id || !inviteForm.user_id" @click="sendInvite">
                        Send Invitation
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
