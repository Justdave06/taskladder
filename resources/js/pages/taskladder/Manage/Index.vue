<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X, Users, FolderPlus, UserPlus, Trash2, Plus, ShieldPlus } from 'lucide-vue-next';
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
import manage from '@/routes/manage';
import team from '@/routes/team';

const projectColors = [
    { label: 'Blue', value: '#3B82F6' },
    { label: 'Red', value: '#EF4444' },
    { label: 'Green', value: '#22C55E' },
    { label: 'Purple', value: '#A855F7' },
    { label: 'Orange', value: '#F97316' },
    { label: 'Pink', value: '#EC4899' },
    { label: 'Teal', value: '#14B8A6' },
    { label: 'Indigo', value: '#6366F1' },
];

interface Member {
    id: number;
    user_id: number;
    status: string;
    role: string | null;
    role_id: number | null;
    user: { id: number; name: string; email: string };
}

interface RoleData {
    id: number;
    project_id: number | null;
    name: string;
}

interface ProjectData {
    id: number;
    title: string;
    description: string | null;
    status: string;
    created_by: number;
    color: string | null;
    creator: { id: number; name: string };
    members: Member[];
    roles: RoleData[];
}

interface AppUser {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    projects: ProjectData[];
    allUsers: AppUser[];
    allRoles: RoleData[];
}>();

const showProjectDialog = ref(false);
const showUserDialog = ref(false);
const showInviteDialog = ref(false);
const projectForm = ref({ title: '', description: '', color: '#3B82F6' });
const userForm = ref({ name: '', email: '', password: '' });
const inviteForm = ref({ project_id: null as number | null, user_id: '', role_id: '' });
const editingRole = ref<{ memberId: number; role: string } | null>(null);
const newRoleName = ref('');
const addingRoleForProject = ref<number | null>(null);
const showRolesForProject = ref<number | null>(null);
const showRoleCreateDialog = ref(false);
    const roleCreateForm = ref({ name: '' });

function roleName(member: Member): string {
    if (member.role_id) {
        const r = props.allRoles.find((r) => r.id === member.role_id);

        if (r) {
return r.name;
}
    }

    return member.role || 'Member';
}

function createRole() {
    if (!newRoleName.value.trim()) {
return;
}

    router.post(manage.roles.store.url(), { name: newRoleName.value }, {
        preserveScroll: true,
        onSuccess: () => {
 newRoleName.value = ''; addingRoleForProject.value = null;
},
    });
}

function deleteRole(roleId: number) {
    if (!confirm('Delete this role?')) {
return;
}

    router.delete(manage.roles.destroy.url({ role: roleId }), { preserveScroll: true });
}

function assignMemberRole(memberId: number, roleId: string) {
    router.patch(manage.members.role.url({ member: memberId }), { role_id: roleId ? Number(roleId) : null }, {
        preserveScroll: true,
    });
}

function createProject() {
    if (!projectForm.value.title.trim()) {
 return;
}

    router.post(manage.projects.store.url(), projectForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showProjectDialog.value = false;
            projectForm.value = { title: '', description: '', color: '#3B82F6' };
        },
    });
}

function deleteProject(id: number) {
    if (!confirm('Delete this project and all its data?')) {
 return;
}

    router.delete(manage.projects.destroy.url({ project: id }), {
        preserveScroll: true,
    });
}

function createUser() {
    if (!userForm.value.name.trim() || !userForm.value.email.trim() || !userForm.value.password) {
 return;
}

    router.post(manage.users.store.url(), userForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            showUserDialog.value = false;
            userForm.value = { name: '', email: '', password: '' };
        },
    });
}

function saveRole(memberId: number, role: string) {
    router.patch(manage.members.update.url({ member: memberId }), { role }, {
        preserveScroll: true,
    });
    editingRole.value = null;
}

function removeMember(memberId: number) {
    if (!confirm('Remove this member from the project?')) {
 return;
}

    router.delete(manage.members.remove.url({ member: memberId }), {
        preserveScroll: true,
    });
}

function startEditRole(memberId: number, currentRole: string | null) {
    editingRole.value = { memberId, role: currentRole || '' };
}

function cancelEditRole() {
    editingRole.value = null;
}

function openInvite(projectId: number) {
    inviteForm.value = { project_id: projectId, user_id: '', role_id: '' };
    showInviteDialog.value = true;
}

function openRoleCreateDialog() {
    roleCreateForm.value = { name: '' };
    showRoleCreateDialog.value = true;
}

function createRoleFromDialog() {
    if (!roleCreateForm.value.name.trim()) {
return;
}

    router.post(manage.roles.store.url(), { name: roleCreateForm.value.name }, {
        preserveScroll: true,
        onSuccess: () => {
            roleCreateForm.value = { name: '' };
            showRoleCreateDialog.value = false;
        },
    });
}

function sendInvite() {
    if (!inviteForm.value.project_id || !inviteForm.value.user_id) {
 return;
}

    const payload: Record<string, any> = { project_id: Number(inviteForm.value.project_id), user_id: Number(inviteForm.value.user_id) };

    if (inviteForm.value.role_id) {
payload.role_id = Number(inviteForm.value.role_id);
}

    router.post(team.invite.url(), payload, {
        preserveScroll: true,
        onSuccess: () => {
 showInviteDialog.value = false;
},
        onError: (errors) => {
            const msgs = Object.values(errors).join('\n');
            alert(msgs || 'Failed to send invitation.');
        },
    });
}

const avatarColors = [
    'bg-blue-100 text-blue-700',
    'bg-indigo-100 text-indigo-700',
    'bg-cyan-100 text-cyan-700',
    'bg-sky-100 text-sky-700',
];
</script>

<template>
    <Head title="Manage" />

    <div class="py-4">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-base font-bold text-blue-900 dark:text-blue-100">Manage</h1>
            <div class="flex gap-2">
                <Button size="sm" variant="outline" class="border-purple-200 text-purple-600 hover:bg-purple-50" @click="openRoleCreateDialog">
                    <ShieldPlus class="mr-1 h-4 w-4" /> Add new role
                </Button>
                <Button size="sm" variant="outline" class="border-blue-200 text-blue-600 hover:bg-blue-50" @click="showProjectDialog = true">
                    <FolderPlus class="mr-1 h-4 w-4" /> Project
                </Button>
                <Button size="sm" class="bg-blue-600 text-white hover:bg-blue-700" @click="showUserDialog = true">
                    <UserPlus class="mr-1 h-4 w-4" /> User
                </Button>
            </div>
        </div>

        <!-- Projects -->
        <div class="mb-6">
            <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-blue-800 dark:text-blue-200">
                <Users class="h-4 w-4" /> Projects
            </h2>

            <div v-if="projects.length === 0" class="rounded-xl border border-dashed border-blue-200 px-4 py-8 text-center text-sm text-gray-400 dark:border-blue-800">
                No projects yet. Create your first project.
            </div>

            <div v-else class="flex flex-col gap-3">
                <Card v-for="project in projects" :key="project.id" class="border-blue-100 dark:border-blue-800">
                    <CardHeader class="pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: project.color || '#3B82F6' }" />
                                <CardTitle class="text-sm text-gray-800 dark:text-gray-200">{{ project.title }}</CardTitle>
                            </div>
                            <div class="flex items-center gap-2">
                                <Button size="sm" variant="outline" class="border-blue-200 text-blue-600 hover:bg-blue-50" @click="openInvite(project.id)">
                                    <UserPlus class="mr-1 h-3 w-3" /> Invite
                                </Button>
                                <Badge variant="outline" class="border-blue-200 text-[10px] text-blue-600 dark:border-blue-700 dark:text-blue-300">
                                    {{ project.members.length }} members
                                </Badge>
                                <button class="text-gray-400 hover:text-red-500" @click="deleteProject(project.id)" title="Delete project">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="project.members.length === 0" class="text-xs text-gray-400">No members. Invite users to this project.</div>
                        <div v-else class="flex flex-col gap-2">
                            <div
                                v-for="(member, idx) in project.members"
                                :key="member.id"
                                class="flex items-center gap-3 rounded-lg border border-blue-100 bg-blue-50/30 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/20"
                            >
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
                                    :class="avatarColors[idx % avatarColors.length]"
                                >
                                    {{ member.user.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ member.user.name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ member.user.email }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button class="text-gray-400 hover:text-red-500" @click="removeMember(member.id)" title="Remove">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Roles management (global) -->
                        <div class="mt-3 border-t border-blue-100 pt-2 dark:border-blue-800">
                            <div class="flex items-center justify-between">
                                <button class="flex items-center gap-1 text-[11px] font-medium text-blue-600 hover:text-blue-700" @click="showRolesForProject = showRolesForProject === project.id ? null : project.id">
                                    <Plus class="h-3 w-3" /> Roles
                                </button>
                                <button v-if="showRolesForProject === project.id && addingRoleForProject !== project.id" class="text-[10px] text-blue-500 hover:underline" @click="addingRoleForProject = project.id">+ Add</button>
                            </div>
                            <div v-if="showRolesForProject === project.id" class="mt-1 flex flex-col gap-1">
                                <div v-if="addingRoleForProject === project.id" class="flex items-center gap-1">
                                    <input
                                        v-model="newRoleName"
                                        class="flex-1 rounded border border-blue-300 px-2 py-1 text-[11px] outline-none focus:border-blue-500"
                                        placeholder="Role name…"
                                        @keydown.enter="createRole()"
                                    />
                                    <button class="text-[10px] font-medium text-blue-600 hover:text-blue-700" @click="createRole()">Save</button>
                                    <button class="text-[10px] text-gray-400 hover:text-gray-600" @click="addingRoleForProject = null; newRoleName = ''">Cancel</button>
                                </div>
                                <div v-for="role in allRoles" :key="role.id" class="flex items-center justify-between rounded bg-blue-50 px-2 py-1 dark:bg-blue-900/20">
                                    <span class="text-[11px] font-medium text-gray-700 dark:text-gray-300">{{ role.name }}</span>
                                    <button class="text-gray-400 hover:text-red-500" @click="deleteRole(role.id)" title="Delete role">
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                                <div v-if="allRoles.length === 0" class="text-[10px] text-gray-400">No roles defined yet.</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Users -->
        <div>
            <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-blue-800 dark:text-blue-200">
                <UserPlus class="h-4 w-4" /> Users
            </h2>

            <div v-if="allUsers.length === 0" class="rounded-xl border border-dashed border-blue-200 px-4 py-8 text-center text-sm text-gray-400 dark:border-blue-800">
                No other users yet. Create users to invite to projects.
            </div>

            <div v-else class="grid grid-cols-2 gap-2">
                <div
                    v-for="user in allUsers"
                    :key="user.id"
                    class="rounded-lg border border-blue-100 bg-white px-3 py-2 dark:border-blue-800 dark:bg-gray-800"
                >
                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ user.name }}</div>
                    <div class="truncate text-xs text-gray-500">{{ user.email }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Project Dialog -->
    <Dialog v-model:open="showProjectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-blue-800 dark:text-blue-200">New Project</DialogTitle>
                <DialogDescription>Create a new project and pick a color.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createProject" class="flex flex-col gap-3">
                <div>
                    <Label for="proj-title" class="text-xs text-gray-600">Project name</Label>
                    <Input id="proj-title" v-model="projectForm.title" placeholder="e.g. HOMI" required class="border-blue-200 focus:border-blue-400" />
                </div>
                <div>
                    <Label class="text-xs text-gray-600">Color</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in projectColors"
                            :key="c.value"
                            type="button"
                            class="h-7 w-7 rounded-full border-2 transition-all"
                            :class="projectForm.color === c.value ? 'border-gray-800 scale-110' : 'border-transparent'"
                            :style="{ backgroundColor: c.value }"
                            :title="c.label"
                            @click="projectForm.color = c.value"
                        />
                    </div>
                </div>
                <div>
                    <Label for="proj-desc" class="text-xs text-gray-600">Description (optional)</Label>
                    <Input id="proj-desc" v-model="projectForm.description" placeholder="Brief description..." class="border-blue-200 focus:border-blue-400" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" class="border-gray-200 text-gray-600" @click="showProjectDialog = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-blue-600 text-white hover:bg-blue-700">Create</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Invite Member Dialog -->
    <Dialog v-model:open="showInviteDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-blue-800 dark:text-blue-200">Invite Member</DialogTitle>
                <DialogDescription>Add a user to this project.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label class="text-xs text-gray-600">User</Label>
                    <select
                        v-model="inviteForm.user_id"
                        class="flex h-10 w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300 dark:border-blue-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="" disabled>Select user</option>
                        <option v-for="u in allUsers" :key="u.id" :value="String(u.id)">
                            {{ u.name }} ({{ u.email }})
                        </option>
                    </select>
                </div>
                <div>
                    <Label class="text-xs text-gray-600">Role (optional)</Label>
                    <select
                        v-model="inviteForm.role_id"
                        class="flex h-10 w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm text-gray-800 outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300 dark:border-blue-700 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="">No role</option>
                        <option v-for="r in allRoles" :key="r.id" :value="String(r.id)">
                            {{ r.name }}
                        </option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" class="border-gray-200 text-gray-600" @click="showInviteDialog = false">Cancel</Button>
                    <Button :disabled="!inviteForm.project_id || !inviteForm.user_id" size="sm" class="bg-blue-600 text-white hover:bg-blue-700" @click="sendInvite">Send Invitation</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Create Role Dialog -->
    <Dialog v-model:open="showRoleCreateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-blue-800 dark:text-blue-200">New Role</DialogTitle>
                <DialogDescription>Add a new role.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label for="role-name" class="text-xs text-gray-600">Role Name</Label>
                    <Input id="role-name" v-model="roleCreateForm.name" placeholder="e.g. Developer" class="border-blue-200 focus:border-blue-400" @keydown.enter="createRoleFromDialog" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" class="border-gray-200 text-gray-600" @click="showRoleCreateDialog = false">Cancel</Button>
                    <Button :disabled="!roleCreateForm.name.trim()" size="sm" class="bg-purple-600 text-white hover:bg-purple-700" @click="createRoleFromDialog">Create</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Create User Dialog -->
    <Dialog v-model:open="showUserDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-blue-800 dark:text-blue-200">New User</DialogTitle>
                <DialogDescription>Create a new user account.</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createUser" class="flex flex-col gap-3">
                <div>
                    <Label for="user-name" class="text-xs text-gray-600">Name</Label>
                    <Input id="user-name" v-model="userForm.name" placeholder="Full name" required class="border-blue-200 focus:border-blue-400" />
                </div>
                <div>
                    <Label for="user-email" class="text-xs text-gray-600">Email</Label>
                    <Input id="user-email" v-model="userForm.email" type="email" placeholder="email@example.com" required class="border-blue-200 focus:border-blue-400" />
                </div>
                <div>
                    <Label for="user-pwd" class="text-xs text-gray-600">Password</Label>
                    <Input id="user-pwd" v-model="userForm.password" type="password" placeholder="Min 8 characters" required class="border-blue-200 focus:border-blue-400" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" class="border-gray-200 text-gray-600" @click="showUserDialog = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-blue-600 text-white hover:bg-blue-700">Create</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
