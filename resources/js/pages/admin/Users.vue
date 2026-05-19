<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Pencil, Trash2, Plus, Building2, Globe } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CompanyData { id: number; name: string; }
interface UserData { id: number; name: string; email: string; is_superadmin: boolean; is_company_admin: boolean; company_id: number | null; company: CompanyData | null; }

const props = defineProps<{
    users: UserData[];
    companies: CompanyData[];
}>();

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const editingUser = ref<UserData | null>(null);
const form = ref({ name: '', email: '', password: '', company_id: '', is_company_admin: false, is_superadmin: false });

function openCreate() {
    form.value = { name: '', email: '', password: '', company_id: '', is_company_admin: false, is_superadmin: false };
    showCreateDialog.value = true;
}

function openEdit(user: UserData) {
    editingUser.value = user;
    form.value = {
        name: user.name,
        email: user.email,
        password: '',
        company_id: user.company_id ? String(user.company_id) : '',
        is_company_admin: user.is_company_admin,
        is_superadmin: user.is_superadmin,
    };
    showEditDialog.value = true;
}

function createUser() {
    router.post('/admin/users', form.value, {
        preserveScroll: true,
        onSuccess: () => { showCreateDialog.value = false; },
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}

function updateUser() {
    if (!editingUser.value) return;
    router.patch(`/admin/users/${editingUser.value.id}`, form.value, {
        preserveScroll: true,
        onSuccess: () => { showEditDialog.value = false; editingUser.value = null; },
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}

function deleteUser(user: UserData) {
    if (!confirm(`Delete user "${user.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/users/${user.id}`, {
        preserveScroll: true,
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}

const companyGroups = computed(() => {
    const groups: { company: CompanyData | null; users: UserData[] }[] = [];
    for (const company of props.companies) {
        const companyUsers = props.users.filter(u => u.company_id === company.id);
        if (companyUsers.length) {
            groups.push({ company, users: companyUsers });
        }
    }
    const unassigned = props.users.filter(u => !u.company_id);
    if (unassigned.length) {
        groups.push({ company: null, users: unassigned });
    }
    return groups;
});
</script>

<template>
    <Head title="Admin Users" />
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-[#0F1623]">Users</h1>
            <Button size="sm" class="bg-[#2563EB] text-white hover:bg-[#1d4ed8]" @click="openCreate">
                <Plus class="mr-1 h-4 w-4" /> Create User
            </Button>
        </div>

        <div class="flex flex-col gap-6">
            <div v-for="group in companyGroups" :key="group.company?.id ?? 'unassigned'" class="overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
                <div class="flex items-center gap-2 border-b border-[#E4E7F0] bg-[#F7F8FC] px-4 py-2.5">
                    <component :is="group.company ? Building2 : Globe" class="h-4 w-4 text-[#5A6278]" />
                    <span class="text-[13px] font-semibold text-[#0F1623]">{{ group.company?.name || 'No Company' }}</span>
                    <span class="ml-auto text-[11px] text-[#9BA3B8]">{{ group.users.length }} user{{ group.users.length !== 1 ? 's' : '' }}</span>
                </div>
                <table class="w-full text-left text-[13px]">
                    <thead class="border-b border-[#E4E7F0] bg-[#F7F8FC]">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">ID</th>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">Name</th>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">Email</th>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">Admin</th>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">Superadmin</th>
                            <th class="px-4 py-3 font-semibold text-[#5A6278]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in group.users" :key="user.id" class="border-b border-[#E4E7F0] last:border-0 hover:bg-[#F7F8FC]">
                            <td class="px-4 py-3 text-[#9BA3B8] text-[11px]">{{ user.id }}</td>
                            <td class="px-4 py-3 font-medium text-[#0F1623]">{{ user.name }}</td>
                            <td class="px-4 py-3 text-[#5A6278]">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <span v-if="user.is_company_admin" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-700">Yes</span>
                                <span v-else class="text-[#9BA3B8]">No</span>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="user.is_superadmin" class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-medium text-red-700">Yes</span>
                                <span v-else class="text-[#9BA3B8]">No</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="rounded-lg p-1.5 text-[#5A6278] transition-colors hover:bg-[#EEF3FF] hover:text-[#2563EB]" title="Edit" @click="openEdit(user)">
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <button class="rounded-lg p-1.5 text-[#5A6278] transition-colors hover:bg-red-50 hover:text-red-500" title="Delete" @click="deleteUser(user)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Dialog v-model:open="showCreateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Create User</DialogTitle>
                <DialogDescription>Create a new user account.</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="createUser">
                <div>
                    <Label class="text-xs text-[#5A6278]">Name</Label>
                    <Input v-model="form.name" required class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Email</Label>
                    <Input v-model="form.email" type="email" required class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Password</Label>
                    <Input v-model="form.password" type="password" required class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Company</Label>
                    <select v-model="form.company_id" class="flex h-10 w-full rounded-lg border border-[#E4E7F0] bg-white px-3 py-2 text-sm outline-none focus:border-[#2563EB]">
                        <option value="">No company</option>
                        <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-[#5A6278]">
                        <input v-model="form.is_company_admin" type="checkbox" class="h-4 w-4 accent-[#2563EB]" />
                        Company Admin
                    </label>
                    <label class="flex items-center gap-2 text-sm text-[#5A6278]">
                        <input v-model="form.is_superadmin" type="checkbox" class="h-4 w-4 accent-[#2563EB]" />
                        Superadmin
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showCreateDialog = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-[#2563EB] text-white">Create</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showEditDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Edit User</DialogTitle>
                <DialogDescription>Update user details.</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-3" @submit.prevent="updateUser">
                <div>
                    <Label class="text-xs text-[#5A6278]">Name</Label>
                    <Input v-model="form.name" required class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Email</Label>
                    <Input v-model="form.email" type="email" required class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">New Password (leave blank to keep current)</Label>
                    <Input v-model="form.password" type="password" class="border-[#E4E7F0]" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Company</Label>
                    <select v-model="form.company_id" class="flex h-10 w-full rounded-lg border border-[#E4E7F0] bg-white px-3 py-2 text-sm outline-none focus:border-[#2563EB]">
                        <option value="">No company</option>
                        <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-[#5A6278]">
                        <input v-model="form.is_company_admin" type="checkbox" class="h-4 w-4 accent-[#2563EB]" />
                        Company Admin
                    </label>
                    <label class="flex items-center gap-2 text-sm text-[#5A6278]">
                        <input v-model="form.is_superadmin" type="checkbox" class="h-4 w-4 accent-[#2563EB]" />
                        Superadmin
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showEditDialog = false">Cancel</Button>
                    <Button type="submit" size="sm" class="bg-[#2563EB] text-white">Save</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
